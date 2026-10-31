<?php

namespace App\TravelServices\Suppliers\FlyErbil\Logics\Flight;

use App\Models\SupplierSession;
use App\TravelServices\Handler;
use App\TravelServices\Logics\Flight\Search as FlightSearch;
use Carbon\Carbon;
use stdClass;

trait Search
{
    use FlightSearch;

    protected $latestSession;

    public function canBedone()
    {
        ///> we need to have access token and X-Refresh-Token to send as headers in search
        ///> we can get them from sign in api. so we call signIn action to call that api and get tose tokens and store them in database
        ///> first we check if there are any tokens already stored in database that are not expired(deleted). if there aren't any we call signin to create a new one

        $activeSession = SupplierSession::where('seller_id', $this->seller->id)->where('expire_on', '>', Carbon::now()->addMinutes($this->defaultSearchValidity))->first();
        if (!empty($activeSession)) {
            // lugInfo('old session', ['seller' => $this->seller, 'session' => $activeSession]);
            $this->supplierSession = $activeSession;
        } else {
            // lugInfo('needs new session', ['seller' => $this->seller, 'session' =>  $activeSession ?? null]);
            $signIn = Handler::signIn($this->seller);
            if (!empty($signIn['supplierSession'])) {
                $this->supplierSession = $signIn['supplierSession'];
            } else {
                $this->addError('No Active Supplier Session');
                return false;
            }
        }
        $this->latestSession = json_decode($this->supplierSession->extras, true);
        return true;
    }

    protected function decodeRawResult()
    {
        $this->rawResult['body'] = preg_replace('/(<ota:OTA_AirLowFareSearchRS)([^>]+)>/', '$1>', $this->rawResult['body']);
        $this->rawResult['body'] = preg_replace('/ota:/m', '', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml), true);
    }

    public function requestGenerator()
    {
        // $this->LugTheResponse = true;
        $auth = $this->seller->api->configs;
        $queryParams = $this->translateQueryParams();
        $depends = $queryParams + ['sellerCurrency' => $this->seller->currency->abb];
        // lugInfo("FlyErbil Search Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('FlyErbil.FlightSearch', $depends)->render(),
            'url' => $this->cleanUrlSlash($auth['url']),
            'headers' => ['Content-Type:application/xml', 'Authorization:Bearer ' . $this->latestSession['accessToken'], 'X-Refresh-Token:' . $this->latestSession['refreshToken']],
            'timeout' => 20,
            'apiMethod' => 'Search'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->saveRawResponse('xml');
        $this->extractSupplierCodes();
        $rawResults = $this->rawResult['decoded']['PricedItineraries']['PricedItinerary'] ?? $this->callException('empty result');
        $this->repelSingleMode($rawResults);

        $itemList = [];
        foreach ($rawResults as $result) {

            ///> we're using marketingAirlineCode for validatingAirlineCode because validatingAirline is not provided in the response
            $validdatingAirlineAbb = $result['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'][0]['FlightSegment']['MarketingAirline']['@attributes']['Code'] ?? ($result['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['FlightSegment']['MarketingAirline']['@attributes']['Code'] ?? $result['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['FlightSegment'][0]['MarketingAirline']['@attributes']['Code']); ///> because of the different in the structure of the arrays formed in 1way and 2way flights we had to use '??'
            $this->airlineFrequency($validdatingAirlineAbb);
            $item = [];

            // > Extracting information about the flights including each leg and segments
            $item['service'] = $this->legModifier($result['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'], $validdatingAirlineAbb);
            if ($item['service'] == false) {
                continue;
            }

            // > Extract pricing Information
            $item['price'] = $this->priceModifier($result['AirItineraryPricingInfo'], $this->getPricingConditions($item['service']));

            ///> list all details need for later actions and booking
            $item['sellerInfo'] = $this->getSellerInfo($item['service']);

            $itemList[] = $item;
        }

        $this->items = $itemList;

        lugInfo('S>>>> FlyErbil search Extractor items - ' . $this->seller->alias, ['supplier raw response items count' => count($rawResults), 'Inserted count' => count($this->items), 'new airline Suppliers' => $this->airlineSuppliers]);
    }

    protected function baggageModifier($rawResults)
    {
        ///> flyErbil does not provide baggage information in the search api
        ///> they are usually available in AirPrice (fareRules) action, in the fare rules' text
    }

    protected function legMapper($leg)
    {
        $segments = $leg['FlightSegment'];
        $this->repelSingleMode($segments);
        $legFlightNumbers = [];
        $resBookDesigCodes = [];
        $cabin = $this->queryParams['cabin'];

        $newLeg = new stdClass();
        $newLeg->airlines = [];
        $newLeg->stops = count($segments) - 1;

        $newLeg->segments = [];

        foreach ($segments as $key => $segment) {
            if ($segment['@attributes']['StopQuantity'] != 0) {
                lugWarning('STOP QUANTITY NOT 0', ['raw segment', [$segment]]);
            }
            $operatingAirline = $this->airlines[$segment['OperatingAirline']['@attributes']['Code']] ?? null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['OperatingAirline']['@attributes']['Code'],
                    'data_reference' => types('supplierDataReference', 'FlyErbil'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }

            $marketingAirline = $this->airlines[$segment['MarketingAirline']['@attributes']['Code']] ?? null;
            if (empty($marketingAirline)) {
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['MarketingAirline']['@attributes']['Code'],
                    'data_reference' => types('supplierDataReference', 'FlyErbil'),
                ];
                return false;
            }

            $departureCity = $this->airports[$segment['DepartureAirport']['@attributes']['LocationCode']]['city']['title'] ?? $segment['DepartureAirport']['@attributes']['LocationCode'];
            $departureDateTime = $this->humanDateTime($segment['@attributes']['DepartureDateTime']);
            $departureAirport = $this->airports[$segment['DepartureAirport']['@attributes']['LocationCode']] ?? $segment['DepartureAirport']['@attributes']['LocationCode'];
            $departureTerminal = $segment['DepartureAirport']['@attributes']['Terminal'] ?? '-'; //> i have not seen any
            $arrivalCity = $this->airports[$segment['ArrivalAirport']['@attributes']['LocationCode']]['city']['title'] ?? $segment['ArrivalAirport']['@attributes']['LocationCode'];
            $arrivalDateTime = $this->humanDateTime($segment['@attributes']['ArrivalDateTime']);
            $arrivalAirport = $this->airports[$segment['ArrivalAirport']['@attributes']['LocationCode']] ?? $segment['ArrivalAirport']['@attributes']['LocationCode'];
            $arivalTerminal = $segment['ArrivalAirport']['@attributes']['Terminal'] ?? '-'; //> i have not seen any
            $baggage = ''; ///> not provided
            $airplane = $this->airplanes[$segment['TPA_Extensions']['Equipment']['@attributes']['AirEquipType']]['title'] ?? $segment['Equipment']['@attributes']['AirEquipType'];
            $duration = $this->getDuration($segment['@attributes']['DepartureDateTime'], $segment['@attributes']['ArrivalDateTime']);
            $afterFlight = $this->between2Time(
                $segment['@attributes']['ArrivalDateTime'],
                $segments[$key + 1]['@attributes']['DepartureDateTime'] ?? $segment['@attributes']['ArrivalDateTime']
            );
            $legFlightNumbers[] = $flightNumber = $segment['@attributes']['FlightNumber'];
            $resBookDesigCodes[] = $resBookDesigCode = $segment['@attributes']['ResBookDesigCode'];

            $newLeg->airlines[] = $marketingAirline['id'];
            $newLeg->segments[$key] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' => $segment['@attributes']['DepartureDateTime']
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' => $segment['@attributes']['ArrivalDateTime']
                ],
                'airline' => $marketingAirline,
                'operatingAirline' => $operatingAirline,
                'cabin' => $cabin,
                'luggage' => $baggage,
                'airplane' => $airplane,
                'duration' => $duration,
                'ticket_type' => 'system',
                'flight_number' => $flightNumber,
                'after_flight_stop' => $afterFlight,
                'resBookDesigCode' => $resBookDesigCode,
                'capacity' => null
            ];
        }
        $legDuration = $this->getLegDuration($newLeg->segments[0]['departure']['raw_time'], end($newLeg->segments)['arrival']['raw_time']);
        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[count($newLeg->segments) - 1]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $cabin,
            'luggage' => '',
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' => $legDuration,
            'connections' => count($segments) - 1,
            'ticket_type' => 'system',
            'flight_number' => implode('/', $legFlightNumbers),
            'after_flight_stop' => '-',
            'resBookDesigCode' => implode('/', $resBookDesigCodes),
            'capacity' => null
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = implode('/', $resBookDesigCodes);
        return $newLeg;
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass();
        ///> price breakDown is not Provided

        $passengersCount = $this->modifiedPassengersTypeCount['adults'] + ($this->modifiedPassengersTypeCount['children'] ?? 0) + ($this->modifiedPassengersTypeCount['infants'] ?? 0);
        $baseFare = $price['ItinTotalFare']['BaseFare']['@attributes']['Amount'];
        $payable = $price['ItinTotalFare']['TotalFare']['@attributes']['Amount'];
        $tax = $payable - $baseFare;
        $newPrice->breakDowns['collectedPassengers']  = [
            'baseFare' => $baseFare / $passengersCount,
            'totalFare' => $pb = $payable / $passengersCount,
            'tax' => $tax / $passengersCount,
            'commission' => 0,
            'payable' =>  $pb,
            'passengerTypeTotal' => [
                'baseFare' => $baseFare,
                'totalFare' => $payable,
                'tax' => $tax,
                'commission' => 0,
                'payable' => $payable,
                'passengersCount' => $passengersCount
            ]
        ];

        $newPrice->currency = $this->seller->currency;
        $newPrice->payable = $payable;
        $newPrice->baseFare = $baseFare;
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function extractSupplierCodes()
    {
        ///> find all the airports airlines and airplanes from the response in our database
        preg_match_all('/DepartureAirport LocationCode="(\w+)"/', $this->rawResult['body'], $departureAirportCodes);
        preg_match_all('/ArrivalAirport LocationCode="(\w+)"/', $this->rawResult['body'], $arrivalAirportCodes);
        preg_match_all('/MarketingAirline Code="(\w+)"/', $this->rawResult['body'], $mairlineCodes); ///>>>>>>>>>>>>>>>>>>>>..>>>>..
        preg_match_all('/AirEquipType="(\w+)"/', $this->rawResult['body'], $equipments);
        preg_match_all('/OperatingAirline (.*?) Code="(\w+)"/', $this->rawResult['body'], $airlineCodes);
        $this->airlines = $this->loadAirLines(array_unique(array_merge($airlineCodes[2], $mairlineCodes[1])));
        $this->airports = $this->loadAirports(array_unique(array_merge($departureAirportCodes[1], $arrivalAirportCodes[1])));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments[1]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult[body]' => $this->rawResult['body'], 'airlines' => $airlineCodes[1], 'airports' => array_merge($departureAirportCodes[1], $arrivalAirportCodes[1]), 'airplanes' => $equipments[1], '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getDuration($departure, $arrival)
    {
        $departure = Carbon::create($departure);
        $arrival = Carbon::create($arrival);
        $mins = $departure->diffInMinutes($arrival);
        return intval($mins / 60) . ":" . ($mins % 60);
    }

    protected function getLegDuration($firstSegDeparture, $lastSegArrival)
    {
        $departure = Carbon::create($firstSegDeparture);
        $arrival = Carbon::create($lastSegArrival);
        $mins = $departure->diffInMinutes($arrival);
        return intval($mins / 60) . ":" . ($mins % 60);
    }

    protected function getSellerInfo($serviceInfo)
    {
        $sellerInfo = [
            'lockable' => false,
            'session' => $this->supplierSession->session,
            'passengersForm' => $this->getPassengersForm(),
            'beforeBooks' => ['PriceQuote' => false],
            'afters' => ['fareRules' => false],
        ];

        $arr = [];
        foreach ($serviceInfo['legs'] as $legIndex => $leg) {
            foreach ($leg['segments'] as $segment) {
                $arr[$legIndex][] = [
                    'DepartureDateTime' => $segment['departure']['raw_time'],
                    'FlightNumber' => $segment['flight_number'],
                    'ResBookDesigCode' => $segment['resBookDesigCode'],
                    'DepartureAirport' => $segment['departure']['airport']['abb'],
                    'ArrivalAirport' => $segment['arrival']['airport']['abb'],
                    'MarketingAirline' => $segment['airline']['supplier_key'],
                ];
            }
        }
        $sellerInfo['bookingRequestData'] = $arr;
        return $sellerInfo;
    }

    protected function getPassengersForm()
    {
        ///> flyErbil needs contact info for the lead passenger, that could be one of the adult, we normally use the 1st adult as the leader.
        ///> flyErbil accept either phone or email (or both) as the contact info. we use email
        ///> for the rest of the passengers(including leader) other than these info (listed below), birthdate and gender can also be sent but they're not required  

        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name']
        ];
        return $this->addPassengerFormItem($items);
    }
}

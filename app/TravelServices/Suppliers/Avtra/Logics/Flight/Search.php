<?php

namespace App\TravelServices\Suppliers\Avtra\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use Carbon\Carbon;
use stdClass;

trait Search
{
    use FlightSearch;

    protected $sellerAirline, $bookingInfo, $isDomesticFlight, $baggageInfo = [];

    protected function getContent()
    {
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Avtra/FlightSearch.xml'));
            $xml = new \SimpleXMLElement($this->rawResult['body']);
            $this->rawResult['decoded'] = json_decode(json_encode($xml), true);
            $this->latestSession = 'fromFile';
            $this->selleraAirline = 'IF';
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }
        ///> we used multiCurls so
        $errors = [];
        $curlCount = count($this->rawResult);
        foreach ($this->rawResult as $index => $rawResult) {
            $num = $index + 1;
            $error = $this->haveSupplierResponseErrorNew($rawResult, ". Search curl $num of $curlCount");
            if (!empty($error)) {
                $errors[] = $error;
                unset($this->rawResult[$index]);
                continue;
            }
            $xml = new \SimpleXMLElement($rawResult['body']);
            $this->rawResult[$index]['decoded'] = json_decode(json_encode($xml), true);
        }
        ///> will addError Only if ALL the curls has error
        if (count($errors) >= $curlCount) {
            $this->addError($errors);
        }
    }

    public function requestGenerator()
    {
        ///> Avtra doesn't provide multiAirportCity search, so we use multi curl
        ///> even if both are airports we make only one call but via multiCurl

        $this->multi = true;
        // $this->LugTheRequest = false;
        // $this->LugTheResponse = true;
        $auth = $this->seller->api->configs;
        $this->sellerAirline = $auth['airlineCode'];
        $translatedQueryParams = $this->translateQueryParams(true);

        ///> only when multiAirportCity is chosen leg['origin']['airports'] exists. if it's empty leg['origin']['abb'] is the airport code
        ///> we don't have multiDestination so maximum 2 legs. and we know that for roundtrip flights the 2nd leg is just the oposite of the first one
        $this->originAirports = $translatedQueryParams['legs'][0]['origin']['airports'] ?? [$translatedQueryParams['legs'][0]['origin']['abb']];
        $this->destinationAirports = $translatedQueryParams['legs'][0]['destination']['airports'] ?? [$translatedQueryParams['legs'][0]['destination']['abb']];
        luginfo("Avtra Search Data ({$this->seller->alias})", ['query_params' => $this->queryParams, 'translatedQueryParams' => $translatedQueryParams, 'auth' => $auth]);

        foreach ($this->originAirports as $originAirport) {
            foreach ($this->destinationAirports as $destinationAirport) {
                $translatedQueryParams['legs'][0]['origin']['abb'] = $originAirport;
                $translatedQueryParams['legs'][0]['destination']['abb'] = $destinationAirport;
                if (!empty($translatedQueryParams['legs'][1])) {
                    $translatedQueryParams['legs'][1]['destination']['abb'] = $originAirport;
                    $translatedQueryParams['legs'][1]['origin']['abb'] = $destinationAirport;
                }
                $this->prepareCalls($auth, $translatedQueryParams);
            }
        }
        return $this->action();
    }

    protected function prepareCalls($auth, $queryParams)
    {
        $depends = $queryParams + $auth + ['time' => Carbon::now()->toIso8601String(), 'sellerCurrency' => $this->seller->currency->abb];
        // lugInfo('Blade Data', [$depends]);
        $this->call[] = [
            'fields' => view('Avtra.FlightSearch', $depends)->render(),
            'url' => $this->cleanUrlSlash($auth['url']) . '/availability/lowfaresearch',
            'timeout' => 20,
            'apiMethod' => 'Search'
        ];
    }

    protected function extractor()
    {
        $this->saveRawResponse('xml');
        $this->extractSupplierCodes();
        $rawResultCount = 0;
        $itineraries = [];
        foreach ($this->rawResult as $rawResult) {
            $rawResults = $rawResult['decoded']['PricedItineraries']['PricedItinerary'] ?? [];
            if (empty($rawResults)) {
                lugWarning("Empty PricedItineraries", [$rawResult]);
                continue;
            }
            $this->repelSingleMode($rawResults);
            $rawResultCount += count($rawResults);
            $itineraries = array_merge($itineraries, $rawResults);
        }
        $this->items = $this->itinerary($itineraries);
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $this->sellerAirline;
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['AirItineraryPricingInfo'];
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = true;
        $info->afterSearch = [];
        $info->beforeBook = [];
        $info->extra = [
            'isDomesticFlight' => $this->isDomesticFlight = $this->isDomestic('Iraq'),
            'bookingRequestData' => $this->bookingRequestData($itinerary)
        ];
        return $info;
    }

    protected function bookingRequestData($result)
    {
        $legs = $result['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'];
        $this->repelSingleMode($legs);
        $bookingInfo = [];
        foreach ($legs as $legIndex => $leg) {
            $segments = $leg['FlightSegment'];
            $this->repelSingleMode($segments);
            foreach ($segments as $segKey => $segment) {
                $bookingInfo[$legIndex][$segKey] = [
                    'OperatingAirline' => $segment['OperatingAirline']['@attributes']['Code'],
                    'departureDateTime' => $segment['@attributes']['DepartureDateTime'],
                    'departureAirport' => $segment['DepartureAirport']['@attributes']['LocationCode'],
                    'arrivalAirport' => $segment['ArrivalAirport']['@attributes']['LocationCode'],
                    'flightNumber' => $segment['@attributes']['FlightNumber'],
                    'ResBookDesigCode' => $segment['@attributes']['ResBookDesigCode'],
                    'RPH' => $segment['@attributes']['RPH'],
                ];
            }
        }
        return $bookingInfo;
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass();

        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;

        $breakDowns = $price['PTC_FareBreakdowns']['PTC_FareBreakdown'];
        $this->repelSingleMode($breakDowns);
        foreach ($breakDowns as $priceDetails) {
            $passengerType = ['ADT' => 'adult', 'CHD' => 'child', 'INF' => 'infant'][$priceDetails['PassengerTypeQuantity']['@attributes']['Code']];
            $typeCount = $priceDetails['PassengerTypeQuantity']['@attributes']['Quantity'];

            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                lugError($passengerType . ' count mismatch', ['query params' => $this->passengersTypeCount, 'result count' => $priceDetails['PassengerTypeQuantity']]);
                $this->addError($passengerType . ' count mismatch');
            } else {
                ${$passengerType . 'Count'} = 'exists';
            }

            $costs = $priceDetails['PassengerFare'];
            $totalTax = array_sum((array) ($costs['Taxes']['Tax'] ?? []));

            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $costs['BaseFare']['@attributes']['Amount'],
                'tax' => $totalTax,
                'totalFare' => $costs['TotalFare']['@attributes']['Amount'],
                'commission' => 0,
                'payable' => $costs['TotalFare']['@attributes']['Amount'],
                'passengerTypeTotal' => [
                    'baseFare' => $costs['BaseFare']['@attributes']['Amount'] * $typeCount,
                    'tax' => $totalTax * $typeCount,
                    'totalFare' => $costs['TotalFare']['@attributes']['Amount'] * $typeCount,
                    'commission' => 0,
                    'payable' => $costs['TotalFare']['@attributes']['Amount'] * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];

            ///> extracting baggageInfo
            $baggageInfo = $costs['FareBaggageAllowance'] ?? [];
            if (empty($baggageInfo)) continue; ///> FlyBaghdad has it but UR doesn't
            $this->repelSingleMode($baggageInfo);
            foreach ($baggageInfo as $bi) {
                $this->baggageInfo[$bi['@attributes']['FlightSegmentRPH']] = (empty($this->baggageInfo[$bi['@attributes']['FlightSegmentRPH']]) ? '' : $this->baggageInfo[$bi['@attributes']['FlightSegmentRPH']] . '-') . $bi['@attributes']['UnitOfMeasureQuantity'] . $bi['@attributes']['UnitOfMeasure'] . '/' . $priceDetails['PassengerTypeQuantity']['@attributes']['Code'];
            }
        }

        ///> checking if all the passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            $this->addError('passengers type mismatch');
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $price['ItinTotalFare']['BaseFare']['@attributes']['Amount'];
        $newPrice->payable = $price['ItinTotalFare']['TotalFare']['@attributes']['Amount'];
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function prepareInfo($itinerary)
    {
        $this->prepareBaggageInfo($itinerary);
    }

    protected function legMapper($leg)
    {
        $segments = $leg['FlightSegment'];
        $this->repelSingleMode($segments);
        $legFlightNumbers = $resBookDesigCodes = [];
        $cabin = $this->queryParams['cabin'];
        $newLeg = new stdClass();
        $newLeg->airlines = [];
        $newLeg->stops = count($segments) - 1;
        $newLeg->segments = [];
        foreach ($segments as $key => $segment) {
            $operatingAirline = $marketingAirline = $this->airlines[$segment['OperatingAirline']['@attributes']['Code']] ?? null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['OperatingAirline']['@attributes']['Code'],
                    'data_reference' => types('supplierDataReference', 'Avtra'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }

            $departureCity = $this->airports[$segment['DepartureAirport']['@attributes']['LocationCode']]['city']['title'] ?? $segment['DepartureAirport']['@attributes']['LocationCode'];
            $departureDateTime = $this->humanDateTime($segment['@attributes']['DepartureDateTime']);
            $departureAirport = $this->airports[$segment['DepartureAirport']['@attributes']['LocationCode']] ?? $segment['DepartureAirport']['@attributes']['LocationCode'];
            $departureTerminal = $segment['DepartureAirport']['@attributes']['Terminal'] ?? '-';

            $arrivalCity = $this->airports[$segment['ArrivalAirport']['@attributes']['LocationCode']]['city']['title'] ?? $segment['ArrivalAirport']['@attributes']['LocationCode'];
            $arrivalDateTime = $this->humanDateTime($segment['@attributes']['ArrivalDateTime']);
            $arrivalAirport = $this->airports[$segment['ArrivalAirport']['@attributes']['LocationCode']] ?? $segment['ArrivalAirport']['@attributes']['LocationCode'];
            $arivalTerminal = $segment['ArrivalAirport']['@attributes']['Terminal'] ?? '-';

            $baggage = $this->baggageInfo[$segment['@attributes']['RPH'] ?? ''] ?? '-';
            $airplane = $this->airplanes[$segment['Equipment']['@attributes']['AirEquipType']]['title'] ?? $segment['Equipment']['@attributes']['AirEquipType'];

            $duration = $this->humanDateTime($segment['@attributes']['Duration']);
            $afterFlight = $this->between2Time(
                $segment['@attributes']['ArrivalDateTime'],
                $segments[$key + 1]['@attributes']['DepartureDateTime'] ?? $segment['@attributes']['ArrivalDateTime']
            );

            $legFlightNumbers[] = $flightNumber = $segment['@attributes']['FlightNumber'];
            $resBookDesigCodes[] = $resBookDesigCode =  $segment['@attributes']['ResBookDesigCode'];

            $rph = $segment['@attributes']['RPH'];

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
                'duration' => $duration['time'],
                'ticket_type' => 'system',
                'flight_number' => $flightNumber,
                'after_flight_stop' => $afterFlight,
                'resBookDesigCode' => $resBookDesigCode,
                'rph' => $rph,
                'capacity' => null
            ];
        }
        $legDuration = $this->between2Time(
            $segments[0]['@attributes']['DepartureDateTime'],
            $segments[count($segments) - 1]['@attributes']['ArrivalDateTime']
        );
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

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality']
        ];
        $data = $this->addPassengerFormItem($items);

        if (!$this->isDomesticFlight) {
            $data = $this->addPassengerFormItem(['all' => ['passport_number', 'passport_expiry', 'passport_country']], $data);
        }
        return $data;
    }

    protected function prepareBaggageInfo($itinerary)
    {
        $breakDowns = $itinerary['AirItineraryPricingInfo']['PTC_FareBreakdowns']['PTC_FareBreakdown'];
        $this->repelSingleMode($breakDowns);
        foreach ($breakDowns as $priceDetails) {
            ///> extracting baggageInfo
            $baggageInfo = $costs['FareBaggageAllowance'] ?? [];
            if (empty($baggageInfo)) continue; ///> FlyBaghdad has it but UR doesn't
            $this->repelSingleMode($baggageInfo);
            foreach ($baggageInfo as $bi) {
                $this->baggageInfo[$bi['@attributes']['FlightSegmentRPH']] = (empty($this->baggageInfo[$bi['@attributes']['FlightSegmentRPH']]) ? '' : $this->baggageInfo[$bi['@attributes']['FlightSegmentRPH']] . '-') . $bi['@attributes']['UnitOfMeasureQuantity'] . $bi['@attributes']['UnitOfMeasure'] . '/' . $priceDetails['PassengerTypeQuantity']['@attributes']['Code'];
            }
        }
    }

    protected function haveSupplierResponseErrorNew($rawResult, $message = '')
    {
        preg_match('/<Error ShortText=(.*?) Code=(.*?)>/ims', $rawResult['body'], $rawErrors);
        if (!empty($rawErrors)) {
            // lugWSResError("Avtra response contain error $message ({$this->seller->alias})", ['supplier-error' => $rawErrors, 'rawResult' => $rawResult]);
            return $rawErrors[1] ?? $rawErrors;
        }
        if ($rawResult['http_code'] != 200) {
            // lugWSResError("Avtra response is invalid $message ({$this->seller->alias})", ['rawResult' => $rawResult]);
            return 'Avtra response is invalid';
        }
        return false;
    }

    protected function extractSupplierCodes()
    {
        foreach ($this->rawResult as $rawResult) {
            preg_match_all('/ LocationCode="(\w+)"/', $rawResult['body'], $ap);
            preg_match_all('/OperatingAirline Code="(\w+)"/', $rawResult['body'], $al);
            preg_match_all('/AirEquipType="(.*?)"/', $rawResult['body'], $eq);
            $airportCodes = array_merge($airportCodes ?? [], $ap[1] ?? []);
            $airlineCodes = array_merge($airlineCodes ?? [], $al[1] ?? []);
            $equipments = array_merge($equipments ?? [], $eq[1] ?? []);
        }
        $this->airports = $this->loadAirports(array_unique($airportCodes));
        $this->airlines = $this->loadAirLines(array_unique($airlineCodes));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult' => $this->rawResult, 'airlines' => array_unique($airlineCodes), 'airports' =>  array_unique($airportCodes), 'airplanes' => array_unique($equipments), '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }
}

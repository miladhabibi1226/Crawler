<?php

namespace App\TravelServices\Suppliers\ITours\Logics\Flight;

use App\TravelServices\Handler;
use App\TravelServices\Logics\Flight\Search as FlightSearch;
use Carbon\Carbon;
use stdClass;

trait Search
{
    use FlightSearch;

    protected $searchKey, $itoursSupplierConnection, $availableSeats;

    protected function canBeDone()
    {
        request()->merge(['modifiedPassengersTypeCount' => $this->modifiedPassengersTypeCount]);
        $availability = Handler::beforeSearch($this->seller, $this->object, 'availability');
        $this->searchKey = $availability['key'] ?? false;
        if (empty($this->searchKey)) {
            return $this->addError("No Search Key");
        }
        if (!$this->authorizeRequest()) {
            return $this->addError('Can not get session');
        }
    }

    protected function getContent()
    {
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/ITours/FlightSearch.json'));
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $this->rawResult['http_code'] = 200;
            return true;
        }

        $tries = 0;
        $startTime = Carbon::now();
        $waitingTime = 0;
        do {
            $tries++;

            $this->LugTheRequest = ($tries == 1);

            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
            $this->manageSupplierConnectionData();

            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $isCompelete =  $this->rawResult['decoded']['result']['isCompleted'] ?? false;
            if ($isCompelete != false) break; //> putting this here to avoid unnessesary sleeping

            $error = $this->haveSupplierResponseError();
            if (!empty($error)) {
                $this->supplierConversaion(($rawError['message'] ?? 'Look At Response') . ' - ' . ($rawError['details'] ?? ''));
                $this->addError($error);
                return false;
            }
            sleep(3);
            $waitingTime = Carbon::now()->diffInSeconds($startTime);
        } while ($waitingTime <= 20);

        $this->supplierConversaion();
        lugInfo("Itours search result collected after $tries tries. Wait time: $waitingTime seconds.", ['is compelete' => $isCompelete, 'result count' => $this->rawResult['decoded']['result']['count'] ?? 0]);
    }

    public function requestGenerator()
    {
        $this->call = [
            'fields' => '',
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . '/api/Partners/Flight/V2/GetAvailability?key=' . $this->searchKey,
            'timeout' => 5,
            'method' => 'GET',
            'apiMethod' => 'Search (multi-attempts)'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded']['result']['pricedItineraries'];
        if (empty($rawResults)) {
            $this->addError('Empty pricedItineraries');
            return;
        }
        if (!$this->checkCurrencyCode()) {
            $this->addError('currency mismatch');
            return;
        }
        $this->saveRawResponse();
        $this->extractSupplierCodes();
        $this->isDomesticFlight = $rawResults[0]['isDomestic'] ?? false;
        $this->items = $this->itinerary($rawResults);
    }

    protected function prepareInfo($itinerary)
    {
        $this->availableSeats = $itinerary['availableSeats'] ?? null;
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['airItinerary']['originDestinationOptions'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        ///> we're using marketingAirlineCode for validatingAirlineCode because validatingAirline is not provided in the response
        return $itinerary['airItinerary']['originDestinationOptions'][0]['flightSegments'][0]['marketingAirline']['code'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['airItineraryPricingInfo'];
    }

    protected function checkCurrencyCode()
    {
        if (!$this->fromFile  && ($this->rawResult['decoded']['result']['currency'] != $this->seller->currency->abb)) {
            lugError('Currency mismatch', ['queryParams' => $this->seller->currency->abb, 'rawResults' => $this->rawResult['decoded']['result']['currency']]);
            $this->addError('Currency mismatch');
            return false;
        }
        return true;
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = $itinerary['hasOnlineVoid'] ?? false;
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = ['validateFlight' => false];
        $info->extra = [
            'isIranianDomesticFlight' => $this->isDomesticFlight,
            'bookingRequestData' => [
                'key' => $itinerary['key']
            ]
        ];
        return $info;
    }

    protected function legMapper($leg)
    {
        $newLeg = new stdClass();
        $newLeg->airlines = $newLeg->segments = $legFlightNumbers = $resBookDesigCodes = [];
        $newLeg->stops = $leg['numberOfStop'];
        $segments = $leg['flightSegments'];
        foreach ($segments as $key => $segment) {
            $operatingAirline = $this->airlines[$segment['operatingAirline']['code']] ?? null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['operatingAirline']['code'],
                    'data_reference' => types('supplierDataReference', 'ITours'),
                    'descriptions' => $segment['operatingAirline']['name']
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }

            $marketingAirline = $this->airlines[$segment['marketingAirline']['code']] ?? null;
            if (empty($marketingAirline)) {
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['marketingAirline']['code'],
                    'data_reference' => types('supplierDataReference', 'ITours'),
                    'descriptions' => $segment['marketingAirline']['name']
                ];
                return false;
            }


            $cabin = $segment['cabinClass']['name'];
            if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) { ///> this way we'll include Premium/standard options in the results
                lugError('Cabin mismatch', ['queryParams' => $this->queryParams['cabin'], 'segment' => $segment]);
                return false;
            }

            $departureCity = $this->airports[$segment['departureAirport']['locationCode']]['city']['title'] ?? $segment['departureAirport']['cityName'];
            $departureDateTime = $this->humanDateTime($segment['departureDateTime']);
            $departureAirport = $this->airports[$segment['departureAirport']['locationCode']] ?? $segment['departureAirport']['locationCode'];
            $departureTerminal = $segment['departureAirport']['terminalID'] ?? '-';

            $arrivalCity = $this->airports[$segment['arrivalAirport']['locationCode']]['city']['title'] ?? $segment['arrivalAirport']['cityName'];
            $arrivalDateTime = $this->humanDateTime($segment['arrivalDateTime']);
            $arrivalAirport = $this->airports[$segment['arrivalAirport']['locationCode']] ?? $segment['arrivalAirport']['locationCode'];
            $arivalTerminal = $segment['arrivalAirport']['terminalID'] ?? '-';

            $baggage = $this->baggageModifier($segment['baggageInformation']);
            $airplane = $this->airplanes[$segment['airEquipType']]['title'] ?? $segment['airEquipType'];
            $duration = $segment['flightDuration'];
            $afterFlight = $segment['stopTime'];
            $legFlightNumbers[] = $flightNumber = $segment['flightNumber'];
            $resBookDesigCodes[] = $resBookDesigCode = $segment['resBookDesigCode'];

            $newLeg->airlines[] = $marketingAirline['id'];
            $newLeg->segments[$key] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' => $segment['departureDateTime']
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' => $segment['arrivalDateTime']
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
                'capacity' => $this->availableSeats
            ];
        }
        $legDuration = $leg['journeyDuration'];
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
            'capacity' => $this->availableSeats
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = implode('/', $resBookDesigCodes);
        return $newLeg;
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass();

        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;

        foreach ($price['ptC_FareBreakdown'] as $priceDetails) {
            $passengerType = ['ADT' => 'adult', 'CHD' => 'child', 'INF' => 'infant'][$priceDetails['passengerTypeQuantity']['code']];
            $typeCount = $priceDetails['passengerTypeQuantity']['quantity'];

            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                lugError($passengerType . ' count mismatch', ['query params' => $this->queryParams, 'result count' => $priceDetails['passengerTypeQuantity']]);
                $this->addError($passengerType . ' count mismatch');
            } else {
                ///> to check later that all the requested passengers types are available in the result
                ${$passengerType . 'Count'} = 'exists';
            }

            $taxAmount = 0;
            foreach ($priceDetails['passengerFare']['taxes'] as $tax) {
                if (in_array($tax['code'], ["Total Tax", "TotalTax"])) {
                    $taxAmount = $tax['amount'];
                }
            }
            $commission = $priceDetails['passengerFare']['commission'] + $priceDetails['passengerFare']['thirdPartyCommission'];

            $newPrice->breakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['passengerFare']['baseFare'],
                'tax' => $taxAmount,
                'totalFare' => $tf = $taxAmount + $priceDetails['passengerFare']['baseFare'],
                'commission' => $commission,
                'payable' => $priceDetails['passengerFare']['totalFare'],
                'passengerTypeTotal' => [
                    'baseFare' => $priceDetails['passengerFare']['baseFare'] * $typeCount,
                    'tax' => $taxAmount * $typeCount,
                    'totalFare' => $tf * $typeCount,
                    'commission' => $commission * $typeCount,
                    'payable' => $ptf = $priceDetails['passengerFare']['totalFare'] * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];
            $passengersTotalFare = ($passengersTotalFare ?? 0) + $ptf;
        }

        ///> checking if all the passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            $this->addError('passengers type mismatch');
        }

        if ($passengersTotalFare != $price['itinTotalFare']['totalFare']) {
            ////> itours team said it might end up being different and in those cases we should consider. itinTotalFare.totalFare as the final price.
            ////> but our calculations are all per passenger so we'll skipp these type of results
            lugWarning('per passenger price is wrong', ['passengersTotalFare' => $passengersTotalFare, 'price' => $price, 'breakDowns' => $newPrice->breakDowns]);
            return false;
        }

        $newPrice->currency = $this->seller->currency; //> we have checked before in extractor(), so we are sure that at this stage the result currency is the seller currency
        $newPrice->payable = $price['itinTotalFare']['totalFare'];
        $newPrice->baseFare = $price['itinTotalFare']['baseFare'];
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;

        return $newPrice;
    }

    protected function baggageModifier($baggageInformation)
    {
        $text = '';
        foreach ($baggageInformation as $passengerType) {
            $text = "$text {$passengerType['baggageAllowance']} {$passengerType['unitType']}/{$passengerType['passengerType']} -";
        }

        return rtrim(ltrim($text), '-');
    }

    protected function extractSupplierCodes()
    {
        ///> find all the airports airlines and airplanes from the response in our database
        preg_match_all('/"locationCode":"(\w+)"/', $this->rawResult['body'], $airportCodes);
        preg_match_all('/Airline":{"code":"(\w+)"/', $this->rawResult['body'], $airlineCodes);
        preg_match_all('/"airEquipType":"(\w+)"/', $this->rawResult['body'], $equipments);
        $this->airlines = $this->loadAirLines(array_unique($airlineCodes[1]));
        $this->airports = $this->loadAirports(array_unique($airportCodes[1]));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments[1]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult[body]' => $this->rawResult['body'], 'airlines' => $airlineCodes[1], 'airports' => $airportCodes[1], 'airplanes' => $equipments[1], '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality']
        ];
        $data = $this->addPassengerFormItem($items);

        if ($this->isDomesticFlight) {
            $data = $this->addPassengerFormItem(['all' => ['domestic_first_name', 'domestic_last_name', 'nationalId']], $data);
        } else {
            $data = $this->addPassengerFormItem(['all' => ['passport_number', 'passport_expiry']], $data);
        }
        return $data;
    }

    protected function manageSupplierConnectionData()
    {
        $this->itoursSupplierConnection = [
            'supplier_service_id' => $this->supplierConnection['supplier_service_id'],
            'pid' => $this->supplierConnection['pid'],
            'method' => $this->supplierConnection['method'],
            'start_time' => $this->itoursSupplierConnection['start_time'] ?? $this->supplierConnection['start_time'],
            'total_time' => ($this->itoursSupplierConnection['total_time'] ?? 0) + $this->supplierConnection['total_time']
        ];
        $this->supplierConnection = $this->itoursSupplierConnection;
    }

    protected function haveSupplierResponseError()
    {
        $rawError = $this->rawResult['decoded']['errors'] ?? null;
        if (!empty($rawError)) {
            $err = ($this->rawResult['decoded']['title'] ?? 'See Response') . '. traceId: ' . ($this->rawResult['decoded']['traceId'] ?? '-');
            lugWSResError("ITours response contains error ({$this->seller->alias})", ['supplier response: ' => $this->rawResult['decoded']]);
            return $err;
        }
        $rawError = $this->rawResult['decoded']['error'] ?? null;
        if (!empty($rawError)) {
            lugWSResError("ITours response contains error ({$this->seller->alias})", ['supplier response: ' => $this->rawResult['decoded']]);
            return $rawError['message'] ?? 'Look At Response';
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("ITours response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            return 'ITours response is invalid';
        }
        return false;
    }
}

<?php

namespace App\TravelServices\Suppliers\TopTravel\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use Carbon\Carbon;
use stdClass;
use Illuminate\Support\Str;


trait Search
{
    use FlightSearch;
    protected $latestSession, $translatedQueryParams, $baggages, $flightSearchKey;

    public function canBeDone()
    {
        $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/TopTravel/FlightSearch.json"));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    protected function requestGenerator()
    {
        $this->translatequeryParams = $this->translateQueryParams();
        $this->call = [
            'fields' => json_encode($this->getFields()),
            'url' => $this->seller->api->configs['url'] . '/satguruapiout/v1/flight/flightSearch',
            'headers' => [
                'Authorization:Bearer ' . $this->latestSession,
                'Content-Type: application/json'
            ],
            'timeout' => 35,
            'apiMethod' => 'Search'
        ];

        return $this->action();
    }

    protected function getFields()
    {
        $airTripType = match ($this->queryParams['tripType']) {
            'oneWay' => 'oneway',
            'roundTrip' => 'roundtrip',
            'multiDestination' => 'multicity'
        };
        $cabin = match ($this->queryParams['cabin']) {
            'Economy' => '1',
            'PremiumEconomy' => '2',
            'Business' => '3',
            'First' => '4',
        };
        foreach ($this->translatequeryParams['legs'] as $legIndex => $leg) {
            $legs[] = [
                "cabinClass" => $cabin,
                "destination" => $leg['destination']['abb'],
                "journeyDate" => Carbon::parse($leg['departure'])->format('d-m-Y'),
                "origin" => $leg['origin']['abb']
            ];
        }
        $auth = $this->seller->api->configs;
        $data = [
            "agencyCode" =>  Str::before($auth['username'], ':'),
            "flightSearchWidgetElement" => $legs,
            "noOfAdults" => (string) $this->translatequeryParams['adults'],
            "noOfChilds" => (string) $this->translatequeryParams['children'],
            "noOfInfants" => (string) $this->translatequeryParams['infants'],
            "tripType" => $airTripType
        ];

        return $data;
    }

    protected function extractor()
    {
        $rawResults = $this->extractResponse();
        if (empty($rawResults)) {
            $this->addError('Empty Items');
            return;
        }
        if (!$this->checkCurrency($rawResults)) {
            $this->addError('Currency Mismatch');
            return;
        }
        $this->flightSearchKey = $this->rawResult['decoded']['flightSearchKey'];
        $this->saveRawResponse();
        $this->extractSupplierCodes();

        $this->items = $this->itinerary($this->recommendation($rawResults));
    }

    protected function extractSupplierCodes()
    {
        preg_match_all('/"operatedByAirline":\s*"([^"]+)"/', $this->rawResult['body'], $oAirlines);
        preg_match_all('/"marketingCarrier":\s*"([^"]+)"/', $this->rawResult['body'], $mAirlines);
        preg_match_all('/"platingCarrier":\s*"([^"]+)"/', $this->rawResult['body'], $pCarriers);
        preg_match_all('/"carrier":\s*"([^"]+)"/', $this->rawResult['body'], $carriers);
        preg_match_all('/"origin":\s*"([^"]+)"/', $this->rawResult['body'], $origins);
        preg_match_all('/"destination":\s*"([^"]+)"/', $this->rawResult['body'], $destinations);
        preg_match_all('/"equipment"\s*:\s*"([^"]+)"/', $this->rawResult['body'], $equipments);
        $this->airlines = $this->loadAirLines(array_unique(array_merge($oAirlines[1], $mAirlines[1], $pCarriers[1], $carriers[1])));
        $this->airports = $this->loadAirports(array_unique(array_merge($origins[1], $destinations[1])));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments[1]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['AirportCodes' => array_unique(array_merge($departureAirportCodes[1], $arrivalAirportCodes[1])), 'AirlineCode' => array_unique(array_merge($airlineCodes[1], $vAirlineCodes[1], $vAirlineCodes[1])), '$equipments' => $equipments, '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function checkCurrency($rawResults)
    {
        if ($this->queryParams['tripType'] === 'oneWay') {
            $currency = $rawResults[0]['flightFare']['currency'];
        } else {
            $currency = $rawResults[0]['currency'];
        }
        if ($currency != $this->seller->currency->abb) {
            lugError('Currency Mismatch', ['sellerCurrency' => $this->seller->currency, 'Currency' =>  $currency ?? null]);
            return false;
        }
        return true;
    }

    protected function recommendation($rawResults)
    {
        $rawResult['legs'] = [];
        $rawResult['FareInfo'] = [];
        foreach ($rawResults  as &$rawResult) {
            if ($this->queryParams['tripType'] == 'oneWay') {
                $rawResult['legs'][0] = $rawResult;
                $rawResult['FareInfo'][0] = $rawResult['flightFare'];
            } else {
                $rawResult['legs'][0] = $rawResult['onwardFlightOption'];
                $rawResult['legs'][1] = $rawResult['returnFlightOption'];
                $rawResult['FareInfo'][0] = $rawResult['onwardFlightOption']['flightFare'];
                $rawResult['FareInfo'][1] = $rawResult['returnFlightOption']['flightFare'];
            }
        }

        return $rawResults;
    }

    protected function legMapper($leg)
    {
        $segments = $leg['flightlegs'];
        $cabin = $this->queryParams['cabin'];
        $newLeg = new stdClass();
        $newLeg->airlines = $newLeg->segments = $legFlightNumbers = $resBookDesigCodes = $capacities = [];
        $newLeg->stops = count($segments) - 1;
        foreach ($segments as $key => $segment) {
            $operatingAirline = $this->airlines[$segment['operatedByAirline']] ?? null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['operatedByAirline'],
                    'data_reference' => types('supplierDataReference', 'TopTravel'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }
            $marketingAirline = $this->airlines[$leg['marketingCarrier']] ?? null;
            if (empty($marketingAirline)) {
                $this->airlineSuppliers[] = [
                    'supplier_key' => $leg['marketingCarrier'],
                    'data_reference' => types('supplierDataReference', 'TopTravel'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }

            $cabin = ['Economy' => 'Economy', 'Business' => 'Business', 'First Class' => 'First'][$segment['cabinClass']] ?? null;
            if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) {
                return false;
            }

            $departureCity = $this->airports[$segment['origin']]['city']['title'] ?? '';
            $departureDateTime = $this->humanDateTime($segment['depDate'] . 'T' . $segment['depTime']);
            $departureAirport = $this->airports[$segment['origin']] ?? '';
            if (empty($departureAirport)) {
                lugWarning('DepartureAirport not found ' . ($segment['origin'] ?? ''), [$segment]);
                return false;
            }
            $departureTerminal = $segment['depTerminal'] ?? '-';

            $arrivalCity = $this->airports[$segment['destination']]['city']['title'] ?? '';
            $arrivalDateTime = $this->humanDateTime($segment['arrDate'] . 'T' . $segment['arrTime']);
            $arrivalAirport = $this->airports[$segment['destination']] ?? '';
            if (empty($arrivalAirport)) {
                lugWarning('ArrivalAirport not found ' . ($segment['destination'] ?? ''), [$segment]);
                return false;
            }
            $baggage = $this->baggageModifier($leg);
            $arrivalTerminal = $segment['arrTerminal'] ?? '-';
            $duration = $segment['journeyDuration'];

            if (empty($segments[$key + 1])) {
                $dateTime = $segment['arrDate'] . 'T' . $segment['arrTime'];
            } else {
                $dateTime = ($segments[$key + 1]['depDate'] . 'T' . $segments[$key + 1]['depTime']);
            }

            $afterFlight = $this->between2Time($segment['arrDate'] . 'T' . $segment['arrTime'], $dateTime);

            $legFlightNumbers[] = $flightNumber = $segment['flightNumber'];
            $resBookDesigCodes[] = $resBookDesigCode = $segment['bookingClass'];
            $capacities[] = $capacity = isset($segment['numSeatsAvailable']) ? (int) $segment['numSeatsAvailable'] : null;
            $newLeg->airlines[] = $marketingAirline['id'];
            $newLeg->segments[$key] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' =>  $segment['depDate'] . 'T' . $segment['depTime'],
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arrivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' =>  $segment['arrDate'] . 'T' . $segment['arrTime'],
                ],
                'airline' => $marketingAirline,
                'operatingAirline' =>  $operatingAirline,
                'cabin' => $this->queryParams['cabin'],
                'luggage' => $baggage,
                'airplane' => '',
                'duration' => $duration,
                'ticket_type' => 'system',
                'flight_number' => $flightNumber,
                'after_flight_stop' => $afterFlight,
                'resBookDesigCode' => $resBookDesigCode,
                'capacity' => $capacity
            ];
        }

        $legDuration = intdiv($leg['totalJourneyDuration'], 60) . ':' . ($leg['totalJourneyDuration'] % 60);

        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[count($newLeg->segments) - 1]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $this->queryParams['cabin'],
            'luggage' => '',
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' => $legDuration,
            'connections' => count($segments) - 1,
            'ticket_type' => 'system',
            'flight_number' => implode('/', $legFlightNumbers),
            'after_flight_stop' => '-',
            'resBookDesigCode' => implode('/', $resBookDesigCodes),
            'capacity' => empty($capacities) ? '-' : min($capacities)
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
        $sumTotalFare = $sumBaseFare = 0;
        foreach ($price['PassengerFare'] as $priceDetails) {
            $passengerType = strtolower($priceDetails['passengerType']);
            $typeCount = $priceDetails['Count'];
            if ($typeCount < 1) {
                continue;
            }
            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                lugError($passengerType . ' count mismatch', ['passengersTypeCount' => $this->passengersTypeCount, 'result count' => $priceDetails['PassengerTypeQuantity']]);
            } else {
                ///> to check later that all the requested passengers types are available in th eresult
                ${$passengerType . 'Count'} = 'exists';
            }
            $tax = $priceDetails['Tax'] + $priceDetails['Fees'];
            $totalFare = $tax +  $priceDetails['BaseFare'];
            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['BaseFare'] / $typeCount,
                'tax' => $tax / $typeCount,
                'totalFare' => $totalFare / $typeCount,
                'commission' => 0,
                'payable' => $totalFare / $typeCount,
                'passengerTypeTotal' => [
                    'baseFare' => $priceDetails['BaseFare'],
                    'tax' => $tax,
                    'totalFare' => $totalFare,
                    'commission' => 0,
                    'payable' => $totalFare,
                    'passengersCount' => $typeCount,
                ],
            ];
            $sumBaseFare += $priceDetails['BaseFare'];
            $sumTotalFare +=  $totalFare;
        }
        ///> checking if all the passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            return false;
        }
        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $sumBaseFare;
        $newPrice->payable = $sumTotalFare;
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }
    protected function originDestinationOption($itinerary)
    {
        return $itinerary['legs'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['legs'][0]['platingCarrier'] ?? ($itinerary['legs'][0]['validatingCarrier'] ?? $itinerary['legs'][0]['carrier']);
    }


    protected function supplierRawPricing($itinerary)
    {
        $itinerary['PassengerFare'] = [];
        $totalADT = ['BaseFare' => 0, 'Tax' => 0, 'Fees' => 0, 'Count' =>  $itinerary['noofAdult'] ?? $this->modifiedPassengersTypeCount['adults']];
        $totalCHD = ['BaseFare' => 0, 'Tax' => 0, 'Fees' => 0, 'Count' =>  $itinerary['noofChild'] ?? $this->modifiedPassengersTypeCount['children'] ?? 0];
        $totalINF = ['BaseFare' => 0, 'Tax' => 0, 'Fees' => 0, 'Count' => $itinerary['noofInfant'] ?? $this->modifiedPassengersTypeCount['infants'] ?? 0];
        foreach ($itinerary['FareInfo'] as $flightFare) {
            $totalADT['BaseFare'] += $flightFare['adultBaseFare'];
            $totalADT['Tax'] += $flightFare['adultTax'];
            $totalADT['Fees'] += $flightFare['adultFees'];

            $totalCHD['BaseFare'] += $flightFare['childBaseFare'];
            $totalCHD['Tax'] += $flightFare['childTax'];
            $totalCHD['Fees'] += $flightFare['childFees'];

            $totalINF['BaseFare'] += $flightFare['infantBaseFare'];
            $totalINF['Tax'] += $flightFare['infantTax'];
            $totalINF['Fees'] += $flightFare['infantFees'];
        }
        $itinerary['FareInfo']['PassengerFare']['ADT'] = array_merge($totalADT, ['passengerType' => 'adult']);
        $itinerary['FareInfo']['PassengerFare']['CHD'] = array_merge($totalCHD, ['passengerType' => 'child']);
        $itinerary['FareInfo']['PassengerFare']['INF'] = array_merge($totalINF, ['passengerType' => 'infant']);

        return $itinerary['FareInfo'];
    }


    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = false;
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = ['fareConfirm' => false, 'recheck' => false];
        $info->extra = [
            'flightSearchKey' => $this->flightSearchKey,
            'flightOptionKey' =>  $itinerary['flightOptionKey'],
            'latestSession' => $this->latestSession
        ];
        return $info;
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['phone', 'text_area', 'email'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'passport_expiry', 'passport_number', 'nationality']
        ];
        return  $this->addPassengerFormItem($items);
    }

    protected function baggageModifier($leg)
    {
        $text = '';
        $baggageTypes = [
            'adultCheckinBaggage'  => 'ADT',
            'childCheckinBaggage'  => 'CHD',
            'infantCheckinBaggage' => 'INF',
        ];
        foreach ($baggageTypes as $key => $type) {
            $text .= $this->extractBaggageWeight($leg, $key, $type);
        }
        return rtrim(ltrim($text), '-');
    }

    protected function extractBaggageWeight($leg, $key, $type)
    {
        $text = '';
        if (!empty($leg[$key])) {
            if (strpos($leg[$key], 'Weight') !== false) {
                preg_match('/\d+/', $leg[$key], $matches);
                if (!empty($matches)) {
                    $text = $matches[0] . " KG/{$type} -";
                }
            } elseif (strpos($leg[$key], 'Number') !== false) {
                preg_match('/\d+/', $leg[$key], $matches);
                if (!empty($matches)) {
                    $text = $matches[0] . " PC/{$type} -";
                }
            }
        }
        return $text;
    }
}

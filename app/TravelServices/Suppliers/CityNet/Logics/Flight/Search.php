<?php

namespace App\TravelServices\Suppliers\CityNet\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use stdClass;
use Carbon\Carbon;

trait Search
{
    use FlightSearch;

    protected $latestSession;

    protected function canBeDone()
    {
        $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        // $this->LugTheResponse = 1;
        $auth = $this->seller->api->configs;
        unset($auth['origin']); ///> so it wont get mixed up with queryParams origin
        $queryParams = $this->translateQueryParams();
        $depends = $auth + $queryParams;
        // lugInfo("CityNet Search Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($auth['url']) . '/flights/search',
            'timeout' => 30,
            'apiMethod' => 'Search'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded']['Items'];
        if (empty($rawResults)) {
            $this->addError('Empty Items');
            return;
        }
        if (!$this->checkCurrency($this->rawResult['decoded'])) {
            $this->addError('Currency Mismatch');
            return;
        }
        $this->saveRawResponse();
        $this->extractSupplierCodes();
        $this->isDomesticFlight = $this->isDomestic();

        foreach ($rawResults as $itinerary) {
            if (count($itinerary['AirItinerary']) == 1) {
                ///> for roundTrip flights, if the count of AirItinerary is 2 it means that it is one way combination
                ///> we don't want that bc at the booking step one of them might be booked and the other may fail and we can't handle that
                $itineraries[] = $itinerary;
                continue;
            }
        }
        $this->items = $this->itinerary($itineraries ?? []);
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = true;
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = ['revalidate' => false];
        $info->extra = [
            'isIranianDomesticFlight' => $this->isDomesticFlight,
            'bookingRequestData' => $itinerary['AirItinerary']
        ];
        if ($itinerary['AirItinerary'][0]['SubsystemName'] == 'charter724') {
            $info->afterSearch['captcha'] = false;
        }
        return $info;
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass();

        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;

        $breakDowns = $price['PTC_FareBreakdowns'];
        foreach ($breakDowns as $priceDetails) {
            $passengerType = ['ADT' => 'adult', 'CHD' => 'child', 'INF' => 'infant'][$priceDetails['PassengerTypeQuantity']['Code']];
            $typeCount = $priceDetails['PassengerTypeQuantity']['Quantity'];

            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                lugError($passengerType . ' count mismatch', ['passengersTypeCount' => $this->passengersTypeCount, 'result count' => $priceDetails['PassengerTypeQuantity']]);
                $this->addError($passengerType . ' count mismatch');
            } else {
                ///> to check later that all the requested passengers types are available in th eresult
                ${$passengerType . 'Count'} = 'exists';
            }

            $costs = $priceDetails['PassengerFare'];

            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $costs['BaseFare'],
                'tax' => $tx = $costs['Taxes'] + $costs['ServiceTax'],
                'totalFare' => $tf = $costs['BaseFare'] + $tx,
                'commission' => $costs['Commission'],
                'payable' => $costs['TotalFare'],
                'passengerTypeTotal' => [
                    'baseFare' => $costs['BaseFare'] * $typeCount,
                    'tax' => $tx * $typeCount,
                    'totalFare' => $tf * $typeCount,
                    'commission' => $costs['Commission'] * $typeCount,
                    'payable' => $costs['TotalFare'] * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];
        }

        ///> checking if all the passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            $this->addError('passengers type mismatch');
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $price['ItinTotalFare']['BaseFare'];
        $newPrice->payable = $price['ItinTotalFare']['TotalFare'];
        $newPrice->diff = $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function legMapper($leg)
    {
        $newLeg = new stdClass();
        $newLeg->segments = $newLeg->airlines = $legFlightNumbers = $resBookDesigCodes = $capacities = [];
        $segments = $leg['FlightSegment'];
        $newLeg->stops = count($segments) - 1;
        foreach ($segments as $key => $segment) {
            $operatingAirline = $this->airlines[$segment['OperatingAirline']['Code']] ?? Null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['OperatingAirline']['Code'],
                    'data_reference' => types('supplierDataReference', 'CityNet'),
                    'descriptions' => $segment['OperatingAirline']['CompanyShortName']
                ];
                return false;
            }
            $marketingAirline = $this->airlines[$segment['MarketingAirline']['Code']] ?? Null;
            if (empty($marketingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['MarketingAirline']['Code'],
                    'data_reference' => types('supplierDataReference', 'CityNet'),
                    'descriptions' => $segment['MarketingAirline']['CompanyShortName']
                ];
                return false;
            }
            $newLeg->airlines[] = $marketingAirline['id'];
            $cabin = $segment['CabinClassCode'];
            if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) { ///> this way we'll include Premium/standard options in the results
                // lugWarning(
                //     'cabin mismatch',
                //     // ['request' => $this->queryParams['cabin'], 'result' => $cabin, 'segment' => $segment]
                // );
                return false;
            }
            $duration = $this->getDuration($segment);
            ///> TS1819 allowing flights with no arrival time only for chabahar
            if (empty($duration) && $marketingAirline['supplier_key'] === 'C9') {
                $duration = '02:00:00';
                $segment['ArrivalDateTime'] = $this->computeC9ArrivalDateTime($duration, $segment['DepartureDateTime']);
            }
            if (empty($duration)) {
                // lugWarning(
                //     'empty JourneyDuration',
                //     // ['segment' => $segment]
                // );
                return false;
            }
            $duration = $this->humanDateTime($duration);
            $times[] = $duration['time'];
            $departureCity = $this->airports[$segment['DepartureAirport']['LocationCode']]['city']['title'] ?? $segment['DepartureAirport']['AirportName'];
            $departureDateTime = $this->humanDateTime($segment['DepartureDateTime']);
            $departureAirport = $this->airports[$segment['DepartureAirport']['LocationCode']] ?? $segment['DepartureAirport']['AirportName'];
            $departureTerminal = $segment['DepartureAirport']['Terminal'] ?? '-';

            $arrivalCity = $this->airports[$segment['ArrivalAirport']['LocationCode']]['city']['title'] ?? $segment['ArrivalAirport']['AirportName'];
            $arrivalDateTime = $this->humanDateTime($segment['ArrivalDateTime']);
            $arrivalAirport = $this->airports[$segment['ArrivalAirport']['LocationCode']] ?? $segment['ArrivalAirport']['AirportName'];
            $arivalTerminal = $segment['ArrivalAirport']['Terminal'] ?? '-';

            $baggage = ($segment['MarketingCabin']['BaggageAllowance']['UnitOfMeasureQuantity'] ?? '') . '/' . ($segment['MarketingCabin']['BaggageAllowance']['UnitOfMeasureCode'] ?? '');
            $airplane = $this->airplanes[$segment['Equipment']['AirEquipType']]['title'] ?? $segment['Equipment']['AirEquipType'];
            $afterFlight = $this->getAfterFlight($segment, $segments[$key + 1] ?? []);
            $times[] = $afterFlight;
            $legFlightNumbers[] = $flightNumber = $segment['FlightNumber'];
            $resBookDesigCodes[] = $resBookDesigCode = $segment['ResBookDesigCode'];
            $capacities[] = $capacity = (int) $segment['SeatsRemaining'] ?? null;

            $newLeg->segments[$key] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' => $segment['DepartureDateTime']
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' => $segment['ArrivalDateTime']
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
                'capacity' => $capacity
            ];
        }
        $legDuration = $this->legDuration($leg['JourneyDuration'], $times);
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
            'capacity' => empty($capacities) ? '-' : min($capacities)
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = implode('/', $resBookDesigCodes);
        return $newLeg;
    }

    protected function  computeC9ArrivalDateTime($duration, $departureDateTime)
    {
        $departureTime = Carbon::parse($departureDateTime);
        $durationParts = explode(':', $duration);
        $departureTime->addHours($durationParts[0])->addMinutes($durationParts[1])->addSeconds($durationParts[2]);
        $arrivalDateTime = $departureTime->format('Y-m-d\TH:i');
        return  $arrivalDateTime;
    }

    protected function extractSupplierCodes()
    {
        ///> find all the airports airlines and airplanes from the response in our database
        preg_match_all('/"LocationCode":"(\w+)"/', $this->rawResult['body'], $airportCodes);
        preg_match_all('/Airline":{"Code":"(\w+)"/', $this->rawResult['body'], $airlineCodes);
        preg_match_all('/"AirEquipType":"(.*?)"/', $this->rawResult['body'], $equipments);
        $this->airlines = $this->loadAirLines(array_unique($airlineCodes[1]));
        $this->airports = $this->loadAirports(array_unique($airportCodes[1]));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments[1]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult[body]' => $this->rawResult['body'], 'airports' =>  $airportCodes[1], 'airlines' => $airlineCodes[1], 'airplanes' => $equipments[1], '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality', 'nationalId', 'passport_number', 'passport_expiry', 'passport_country']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function getFields($depends)
    {
        foreach ($depends['legs'] as $legIndex => $leg) {
            $OriginDestinationInformation[] = [
                'OriginLocation' => [
                    'CodeContext' => 'IATA',
                    'LocationCode' => $leg['origin']['abb'],
                    'MultiAirportCityInd' => ($leg['origin']['isCity'] == "true") ?  true : false
                ],
                'DestinationLocation' => [
                    'CodeContext' => 'IATA',
                    'LocationCode' => $leg['destination']['abb'],
                    'MultiAirportCityInd' => ($leg['destination']['isCity'] == "true") ?  true : false
                ],
                'DepartureDateTime' => $leg['departure'],
                'ArrivalDateTime' => null
            ];
        }
        $fields = [
            'Lang' => 'FA',
            'TravelPreference' => [
                'CabinPref' => ['Cabin' => $depends['cabin']],
                'EquipPref' => ['AirEquipType' => 'IATA'],
                'FlightTypePref' => [
                    'BackhaulIndicator' => '',
                    'DirectAndNonStopOnlyInd' => false,
                    'ExcludeTrainInd' => false,
                    'GroundTransportIndicator' => false,
                    'MaxConnections' => 3
                ],
            ],
            'TravelerInfoSummary' => [
                'AirTravelerAvail' => [
                    'PassengerTypeQuantity' => [
                        ['Code' => 'ADT', 'Quantity' => (int) $depends['adults']],
                        ['Code' => 'CHD', 'Quantity' => (int) $depends['children']],
                        ['Code' => 'INF', 'Quantity' => (int) $depends['infants']],
                    ]
                ]
            ],
            'SpecificFlightInfo' => ['Airline' => []],
            'OriginDestinationInformations' => $OriginDestinationInformation
        ];
        return $fields;
    }

    public function loadAirPlanes($airplanecodes)
    {
        $preferedDataReference = 'Amadeus';
        $supplierairplanes = $this->seller->getSupplierAirplanes($preferedDataReference)->whereIn('supplier_key', $airplanecodes)->with('airplane')->get();
        $airplanes = [];
        foreach ($supplierairplanes as $row) {
            $airplanes[$row->airplane->title] = $row->airplane->toArray();
        }
        return $airplanes;
    }

    protected function checkCurrency($result)
    {
        $resultCurrency = $result['Items'][0]['AirItineraryPricingInfo']['ItinTotalFare']['Currency'] ?? null;
        if ($resultCurrency != $this->seller->currency->abb) {
            lugWarning(__METHOD__, [$result]);
            lugError('Currency Mismatch', ['sellerCurrency' => $this->seller->currency, 'result' => $result['Items'][0]['AirItineraryPricingInfo']['ItinTotalFare'] ?? null]);
            return false;
        }
        return true;
    }

    protected function getAfterFlight($segment, $nextSegment)
    {
        if (!empty($segment['ConnectionTime'])) {
            return $segment['ConnectionTime'];
        }
        if (!empty($segment['ConnectionTimePerMinute'])) {
            return intval($segment['ConnectionTime'] / 60) . ":" . ($segment['ConnectionTime'] % 60);
        }
        return $this->between2Time(
            $segment['ArrivalDateTime'],
            $nextSegment['DepartureDateTime'] ?? $segment['ArrivalDateTime']
        );
    }

    protected function getDuration($segment)
    {
        if (!empty($segment['JourneyDuration'])) {
            return $segment['JourneyDuration'];
        }
        if (!empty($segment['JourneyDurationPerMinute'])) {
            return intval($segment['JourneyDurationPerMinute'] / 60) . ":" . ($segment['JourneyDurationPerMinute'] % 60);
        }
        return false;
    }

    protected function legDuration($fromSupplier, $segmentsTimes)
    {
        if (!empty($fromSupplier)) {
            return $fromSupplier;
        }
        $h = $m = 0;
        foreach ($segmentsTimes as $time) {
            $times = explode(':', $time);
            $h += (int) ($times[0] ?? 0);
            $m += (int) ($times[1] ?? 0);
        }

        return intval(($m / 60) + $h) . ":" . ($m % 60);
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['OriginDestinationInformation']['OriginDestinationOption'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['OriginDestinationInformation']['OriginDestinationOption'][0]['FlightSegment'][0]['MarketingAirline']['Code'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['AirItineraryPricingInfo'];
    }
}

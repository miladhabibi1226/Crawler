<?php

namespace App\TravelServices\Suppliers\Kplus\Logics\Flight;

use App\TravelServices\Logics\Flight\OpenReturn;
use App\TravelServices\Logics\Flight\Search as FlightSearch;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\Airline;
use stdClass;

trait Search
{
    use FlightSearch, Recomendations, OpenReturn {
        OpenReturn::getContent insteadof FlightSearch;
        OpenReturn::getSystemExceptions insteadof FlightSearch;
        OpenReturn::checkServiceInfoValidity as OpenReturnCheckServiceInfoValidity;
        FlightSearch::checkServiceInfoValidity as mainCheckServiceInfoValidity;
    }
    protected $baggageInfo, $translatedQueryParams = [], $openReturnDateRange = 3;
    protected $airTripType = null;
    public function canBeDone()
    {
        if (!$this->findOrCreateSupplierSession()) {
            $this->addError('can not get session');
        }
        $this->translatedQueryParams = $this->translateQueryParams();
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded']['Result']['SearchResults'] ?? [];
        if (empty($rawResults)) {
            $this->addError('Empty SearchResults');
            return;
        }
        if (!$this->checkCurrencyCode()) {
            $this->addError('currency mismatch');
            return;
        }
        $this->saveRawResponse();
        $this->extractSupplierCodes();
        $this->items = $this->itinerary($this->getRecommendations($rawResults));
    }

    protected function checkCurrencyCode()
    {
        if (($this->rawResult['decoded']['Result']['SearchResults'][0]['Results'][0]['Fares'][0]['TotalPrice']['CurrencyCode'] ?? null) != $this->seller->currency->abb) {
            lugError('currency mismatch', ['result' => $this->rawResult['decoded']['Result']['SearchResults'][0]['Results'][0]['Fares'][0]['TotalPrice']['CurrencyCode'] ?? null, 'seller' => $this->seller->currency->abb]);
            return false;
        }
        return true;
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = true;
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = ['getBrandedFares' => false, 'revalidate' => false];
        $info->extra = [
            'isTurkeyDomesticFlight' => $this->isDomesticFlight = $this->isDomestic('Turkey'),
            'searchKeys' => $itinerary['keys'],
            'segReferences' => $itinerary['segReferences']
        ];
        return $info;
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass();

        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;

        $breakDowns = $price['PassengerFares'];
        foreach ($breakDowns as $priceDetails) {
            $passengerType = [0 => 'adult', 1 => 'child', 2 => 'infant'][$priceDetails['PassengerType']];
            $typeCount = $priceDetails['Count'];

            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                lugError($passengerType . ' count mismatch', ['passengersTypeCount' => $this->passengersTypeCount, 'result count' => $priceDetails['PassengerTypeQuantity']]);
            } else {
                ///> to check later that all the requested passengers types are available in th eresult
                ${$passengerType . 'Count'} = 'exists';
            }

            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['Price']['BaseAmount'],
                'tax' => $priceDetails['Price']['ServiceFee'] + $priceDetails['Price']['TaxAmount'],
                'totalFare' => $priceDetails['Price']['TotalStrikeAmount'],
                'commission' => $priceDetails['Price']['Commission'],
                'payable' => $priceDetails['Price']['TotalAmount'],
                'passengerTypeTotal' => [
                    'baseFare' => $priceDetails['TotalPrice']['BaseAmount'],
                    'tax' => $priceDetails['TotalPrice']['ServiceFee'] + $priceDetails['TotalPrice']['TaxAmount'],
                    'totalFare' => $priceDetails['TotalPrice']['TotalStrikeAmount'],
                    'commission' => $priceDetails['TotalPrice']['Commission'],
                    'payable' => $priceDetails['TotalPrice']['TotalAmount'],
                    'passengersCount' => $typeCount
                ]
            ];
        }

        ///> checking if all the passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            return false;
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $price['TotalPrice']['BaseAmount'];
        $newPrice->payable = $price['TotalPrice']['TotalAmount'];
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function legMapper($leg)
    {
        $newLeg = new stdClass();
        $newLeg->segments = $newLeg->airlines = $legFlightNumbers = $resBookDesigCodes = $capacities = [];
        $segments = $leg['Segments'];
        $newLeg->stops = count($segments) - 1;
        foreach ($segments as $key => $segment) {
            $operatingAirline = $this->airlines[$segment['OperatingAirline']['Code']] ?? Null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['OperatingAirline']['Code'],
                    'descriptions' => $segment['OperatingAirline']['Name'],
                    'data_reference' => types('supplierDataReference', 'Kplus')
                ];
                return false;
            }
            $marketingAirline = $this->airlines[$segment['TicketingAirline']['Code']] ?? Null;
            if (empty($marketingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['TicketingAirline']['Code'],
                    'descriptions' => $segment['TicketingAirline']['Name'],
                    'data_reference' => types('supplierDataReference', 'Kplus')
                ];
                return false;
            }
            ///> In KPlus, if a flight has more than 1 segment and includes Iraqi Airways(IA) as a carrier,
            // we will ignore it.
            // Task number in planner: TS1268
            if (($marketingAirline['abb'] === 'IA') && (count($segments) > 1)) {
                // lugWarning('flight has more than 1 segment and includes IraqiAirways(IA)', ['segments' => $segments, 'marketingAirline' => $marketingAirline]);
                return false;
            }
            $cabin = [0 => 'All', 1 => 'Promo', 2 => 'First', 3 => 'Business', 4 => 'Economy', 5 => 'Premium First', 6 => 'Premium Economy', 7 => 'Premium', 8 => 'Premium Business'][$segment['fareInfo']['CabinType']];
            if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) { ///> this way we'll include Premium options in the results
                // lugWarning(
                //     'cabin mismatch',
                //     ['request' => $this->queryParams['cabin'], 'result' => $cabin, 'segment' => $segment]
                // );
                return false;
            }

            $departureAirport = $this->airports[$segment['DepartureAirport']['Code']] ?? '';
            if (empty($departureAirport)) { ///> means the Airport is not in database
                lugWarning('DepartureAirport not found ' . ($segment), []);
                return false;
            }
            $arrivalAirport = $this->airports[$segment['ArrivalAirport']['Code']] ?? '';
            if (empty($arrivalAirport)) { ///> means the Airport is not in database
                lugWarning('ArrivalAirport not found ' . ($segment), []);
                return false;
            }
            $departureCity = $this->airports[$segment['DepartureAirport']['Code']]['city']['title'] ?? $segment['DepartureAirport']['Name'];
            $arrivalCity = $this->airports[$segment['ArrivalAirport']['Code']]['city']['title'] ?? $segment['ArrivalAirport']['Name'];
            $departureDateTime = $this->humanDateTime($segment['DepartureDate']);
            $arrivalDateTime = $this->humanDateTime($segment['ArrivalDate']);
            $departureTerminal = '-';
            $arivalTerminal = '-';
            $bags = [];
            foreach ($segment['baggageInfo'] as $passengerType => $bag) {
                $bags[] = "{$bag['Value']}" . ([0 => 'Kilogram', 1 => 'Piece', 2 => 'Pound',][$bag['Type']]) . " / $passengerType";
            }

            $baggage = implode(' -', $bags);
            $airplane = $this->airplanes[$segment['Equipment']['Code']]['title'] ?? $segment['Equipment']['Code'];
            $Times[] = $segment['FlightDuration'];
            $duration = intdiv($segment['FlightDuration'], 60) . ':' . ($segment['FlightDuration'] % 60);
            $Times[] = $between2Time = $this->timeDiffInMinnutes(
                $segment['ArrivalDate'],
                $segments[$key + 1]['DepartureDate'] ?? $segment['ArrivalDate']
            );
            $afterFlight = intdiv($between2Time, 60) . ':' . ($between2Time % 60);

            $legFlightNumbers[] = $flightNumber = $segment['FlightNo'];
            $resBookDesigCodes[] = $resBookDesigCode = $segment['fareInfo']['ClassCode'];
            $capacities[] = $capacity = ((int) $segment['fareInfo']['SeatCount']) ?? null;

            $newLeg->airlines[] = $marketingAirline['id'];
            $newLeg->segments[$key] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' => $segment['DepartureDate']
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' => $segment['ArrivalDate']
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
                'capacity' => $capacity,
                'FareBasis' => $segment['fareInfo']['FareBasis']
            ];
        }
        $Times = array_sum($Times);
        $legDuration = intdiv($Times, 60) . ':' . ($Times % 60);
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

    protected function extractSupplierCodes()
    {
        ///> had to use foreach bc can't get the airline codes via regex
        foreach ($this->rawResult['decoded']['Result']['SearchResults'] as $SearchResult) {
            foreach ($SearchResult['Results'] as $Result) {
                foreach ($Result['Legs'] as $Leg) {
                    foreach ($Leg['AlternativeLegs'] as $AlternativeLeg) {
                        foreach ($AlternativeLeg['Segments'] as $Segment) {
                            $AirlineCodes[] = $Segment['OperatingAirline']['Code'];
                            $AirlineCodes[] = $Segment['TicketingAirline']['Code'];
                            $AirportCodes[] = $Segment['ArrivalAirport']['Code'];
                            $AirportCodes[] = $Segment['DepartureAirport']['Code'];
                            $equipments[] = $Segment['Equipment']['Code'];
                        }
                    }
                }
            }
        }
        $this->airlines = $this->loadAirLines(array_unique($AirlineCodes));
        $this->airports = $this->loadAirports(array_unique($AirportCodes));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult[body]' => $this->rawResult['body'], 'airlines' => array_unique($AirlineCodes), 'airports' => array_unique($AirportCodes), 'airplanes' => array_unique($equipments), '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality', 'passport_expiry', 'passport_number']
        ];
        $data = $this->addPassengerFormItem($items);
        if ($this->isDomesticFlight) {
            $data = $this->addPassengerFormItem(['all' => ['nationalId']], $data);
        }
        return $data;
    }

    protected function getFields($depends, $openReturn = null)
    {
        $this->airTripType = match ($this->translatedQueryParams['tripType']) {
            'oneWay' => 0,
            'roundTrip' => 1,
            'openReturn' => 1,
            'multiDestination' => 2
        };
        // If the search request includes the IATA code of a city,
        //the response may also include airports of that city. 
        //Therefore, in the target search in roundtrip mode, 
        //the IATA codes may be different, and KPlus considers this case as multi.
        //example of target-search request:BGW->IST,SAW->BGW 
        //So, we wrote checkRoundTripMismatch function:
        $this->checkRoundTripMismatch($depends);
        foreach ($depends['legs'] as $legIndex => $leg) {
            $legData = [
                "DeparturePoint" => [
                    "Code" => $leg['origin']['abb'] . ($leg['origin']['isCity'] ? 'ALL' : ''),
                    "HotpointType" =>  1
                ],
                "ArrivalPoint" => [
                    "Code" => $leg['destination']['abb'] . ($leg['destination']['isCity'] ? 'ALL' : ''),
                    "HotpointType" => 1
                ]
            ];
            if (!empty($openReturn) &&  $legIndex == 1) {
                $legData["Date"] = Carbon::create($openReturn)->format('d.m.Y');
            } else {
                $legData["Date"] = Carbon::create($leg['departure'])->format('d.m.Y');
            }
            $legs[] = $legData;
        }
        $fields = [
            "request" => [
                "AdvancedOptions" => [
                    "Air" => [
                        "OnlyBestFares" => false,
                        "OnlyDirectFlights" => false,
                        "OnlyRefundableFlights" => false,
                        "PermittedCabins" => [['First' => 2, 'Business' => 3, 'Economy' => 4, 'PremiumEconomy' => 6][$depends['cabin']]]
                    ]
                ],
                "Legs" => $legs,
                "Passengers" => [
                    ["Count" => $depends['adults'], "PaxType" => 0]
                ],
                "SearchType" => (int) $this->airTripType,
                "RequestId" => createReferenceId(),
                "Token" => ["TokenCode" => $this->latestSession]
            ]
        ];
        if ($depends['children'] > 0) {
            $fields['request']['Passengers'][] = ["Count" => $depends['children'], "PaxType" => 1];
        }
        if ($depends['infants'] > 0) {
            $fields['request']['Passengers'][] = ["Count" => $depends['infants'], "PaxType" => 2];
        }
        return $fields;
    }

    protected function checkRoundTripMismatch($depends)
    {
        if (($this->translatedQueryParams['tripType'] ?? '') === 'roundTrip') {
            $originLeg0 = $depends['legs'][0]['origin']['abb'];
            $destinationLeg0 = $depends['legs'][0]['destination']['abb'];
            $originLeg1 = $depends['legs'][1]['origin']['abb'];
            $destinationLeg1 = $depends['legs'][1]['destination']['abb'];
            if ($originLeg0 !== $destinationLeg1 || $destinationLeg0 !== $originLeg1) {
                $this->airTripType = 2;
            }
        }
    }




    protected function originDestinationOption($itinerary)
    {
        return $itinerary['legs'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['legs'][0]['Segments'][0]['TicketingAirline']['Code'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['priceInfo'];
    }

    protected function timeDiffInMinnutes($start, $end)
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);
        return $end->diffInMinutes($start);
    }

    protected function getSystemExceptions()
    {
        ///> openReturn flight are only available for iraqi airways airline so we'll filter the rest
        if ($this->queryParams['tripType'] == 'openReturn') {
            static $iraqiAirways;
            if (empty($iraqiAirways)) {
                $iraqiAirways = $this->airlines['IA'] ?? Airline::whereAbb('IA')->first()->toArray();
            }
            return ['airline_id' => $iraqiAirways['id'] ?? ''];
        }
        return [];
    }

    protected function searchUrl()
    {
        return '/Air.svc/Rest/Json/SearchAvailability';
    }

    protected function getHeader()
    {
        return  [];
    }

    protected function checkOpenReturnEmptyResult($openReturnResult)
    {
        return empty($openReturnResult['decoded']['Result']['SearchResults']);
    }

    protected function checkServiceInfoValidity($legs)
    {
        $or = true;
        if ($this->queryParams['tripType'] === 'openReturn') {
            $or = $this->OpenReturnCheckServiceInfoValidity($legs);
        }
        return $or && $this->mainCheckServiceInfoValidity($legs);
    }
}

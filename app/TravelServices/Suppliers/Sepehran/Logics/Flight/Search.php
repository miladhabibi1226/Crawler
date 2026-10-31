<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use stdClass;
use Carbon\Carbon;


trait Search
{
    use FlightSearch;
    protected $latestSession, $translatedQueryParams;

    public function canBeDone()
    {
        //check flightSchedule
        $flightSchedule = getcachedItem('supplier.routes.sepehran');
        if (!empty($flightSchedule)) {
            if (!$this->checkFlightSchedule($flightSchedule)) {
                $this->addError("flight is not in annual schedule ");
                return false;
            }
        }
        //check session 
        if (!$this->findOrCreateSupplierSession()) {
            return $this->addError('can not get session');
        }
      
        return true;
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $travelService = ucfirst($this->travelService);
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/Sepehran/{$travelService}Search.json"));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }

        foreach ($this->rawResult as $index => $rawResult) {
            $this->rawResult[$index]['decoded'] = json_decode($rawResult['body'], true);
        }

        $resultErrors  = $this->haveSupplierResponseError();
        foreach ($this->rawResult as $index => $rawResult) {
            if (!empty($resultErrors[$index])) {
                unset($this->rawResult[$index]);
            }
        }
    }

    protected function requestGenerator()
    {

        // $this->LugTheResponse = 1;
        $this->multi = true;
        $auth = $this->seller->api->configs;
        $queryParams = $this->translateQueryParams(true);

        ///> only when multiAirportCity is chosen leg['origin']['airports'] exists. if it's empty leg['origin']['abb'] is the airport code
        ///> we don't have multiDestination so maximum 2 legs. and we know that for roundtrip flights the 2nd leg is just the oposite of the first one
        $originAirports = $queryParams['legs'][0]['origin']['airports'] ?? [$queryParams['legs'][0]['origin']['abb']];
        $destinationAirports = $queryParams['legs'][0]['destination']['airports'] ?? [$queryParams['legs'][0]['destination']['abb']];
        // luginfo("faranegar Search Data ({$this->seller->alias})", ['translatedQueryParams' => $queryParams, 'auth' => $auth]);

        foreach ($originAirports as $originAirport) {
            foreach ($destinationAirports as $destinationAirport) {
                $queryParams['legs'][0]['origin']['abb'] = $originAirport;
                $queryParams['legs'][0]['destination']['abb'] = $destinationAirport;
                if (!empty($queryParams['legs'][1])) {
                    $queryParams['legs'][1]['destination']['abb'] = $originAirport;
                    $queryParams['legs'][1]['origin']['abb'] = $destinationAirport;
                }
                $this->prepareCalls($auth, $queryParams);
            }
        }

        return $this->action();
    }

    protected function prepareCalls($auth, $queryParams)
    {
        $depends = $queryParams + $auth;
        $this->call[] = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($auth['url']) . '/api/v2/flight/FlightSearch',
            'apiMethod' => 'Search',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'timeout' => 15
        ];
    }

    protected function getFields($depends)
    {
        $data = [
            "outbound" => [
                "date" => $depends['legs'][0]['departure'],
                "departure" => $depends['legs'][0]['origin']['abb'],
                "arrival" => $depends['legs'][0]['destination']['abb']
            ],

            "adult" => $depends['adults'],
            "child" => $depends['children'],
            "inf" => $depends['infants']
        ];

        if (!empty($depends['legs'][1])) {
            $data['return'] = [
                "date" => $depends['legs'][1]['departure'],
                "departure" =>  $depends['legs'][1]['origin']['abb'],
                "arrival" => $depends['legs'][1]['destination']['abb']
            ];
        }
        return $data;
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = false;
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = ['price' => false , 'checkCapacity' => false];
        $info->extra = [
            'isDomesticFlight' => $this->isDomesticFlight,
            'refNumbers' => [
                'outbound' => $itinerary[0]['ref_number'] ?? '',
                'return' => $itinerary[1]['ref_number'] ?? '',
            ]
        ];
        return $info;
    }

    protected function legMapper($leg)
    {
        $segment = $leg;
        ///> sepehran only has one segment
        $cabin = $this->queryParams['cabin'];
        $newLeg = new stdClass();
        $newLeg->airlines = $newLeg->segments = $legFlightNumbers = $resBookDesigCodes = $capacities = [];
        $newLeg->stops = 0;


        $airline = $this->airlines[$segment['flight_details']['airline']] ?? null;
        if (empty($airline)) { ///> means the airline is not in database
            $this->airlineSuppliers[] = [
                'supplier_key' => $segment['flight_details']['airline'],
                'data_reference' => $this->dataReference,
            ];
            return false; ///> the result will be ignored if the airline doesn't exist
        }


        $cabin = ['Y' => 'Economy', 'B' => 'Business', 'F' => 'First'][$segment['flight_details']['cabin']] ?? null; ///> we're counting Premium Economy(2) as Economy
        if (strtoupper($cabin) != strtoupper($this->queryParams['cabin'])) {
            // lugWarning(
            //     'Cabin Mismatch',
            //     // [$segment, [1 => 'Economy', 2 => 'Economy', 3 => 'Business', 4 => 'Premium Business', 5 => 'First', 6 => 'Premium First', 100 => 'Default']]
            // );
            return false;
        }

        $duration = $segment['duration'];

        $departureCity = $this->airports[$segment['departure']['location_code']]['city']['title'] ?? '';
        $departureDateTime = $this->humanDateTime($segment['departure']['date'] . $segment['departure']['time']);
        $departureAirport = $this->airports[$segment['departure']['location_code']] ?? $segment['departure']['location_code'];
        $departureTerminal = $segment['departure']['terminal'] ?? '-';

        //timezone
        // $arrivalCountryCode = $this->airports[$segment['arrival']['location_code']]['city']['country']['abb'];
        // $depurtureCountryCode = $this->airports[$segment['departure']['location_code']]['city']['country']['abb'];
        // $segment['arrival']['time'] = $this->changeArrivalTimezone($arrivalCountryCode, $depurtureCountryCode, $segment['arrival']['time']);

        $arrivalCity = $this->airports[$segment['arrival']['location_code']]['city']['title'] ?? $segment['arrival']['location_code'];
        $arrivalDateTime = $this->humanDateTime($segment['arrival']['date'] . $segment['arrival']['time']);
        $arrivalAirport = $this->airports[$segment['arrival']['location_code']] ?? $segment['arrival']['location_code'];
        $arivalTerminal = $segment['arrival']['terminal'] ?? '-';

        foreach ($segment['baggages'] as $key => $baggages) {
            $baggage[] = $baggages['Quantity'] . $baggages['Unit'] . '/' . $baggages['Type'];
        }
        $capacities[] = $capacity = (int) $segment['capacity'] ?? null;
        $airplane = $this->airplanes[$segment['flight_details']['airplane']]['title'] ?? $segment['flight_details']['airplane'];
        $legFlightNumbers[] = $flightNumber = $segment['flight_details']['flight_number'];
        $resBookDesigCodes[] = $resBookDesigCode = $segment['flight_details']['class'];
        $afterFlight = $this->between2Time($segment['arrival']['date'] . $segment['arrival']['time'], null);

        $newLeg->airlines[] = $airline['id'];
        $newLeg->segments = [[
            'departure' => [
                'city' => $departureCity,
                'time' => $departureDateTime['time'],
                'airport' => $departureAirport,
                'terminal' => $departureTerminal,
                'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                'raw_time' => $segment['departure']['date'] . 'T' . $segment['departure']['time'],

            ],
            'arrival' => [
                'city' => $arrivalCity,
                'time' => $arrivalDateTime['time'],
                'airport' => $arrivalAirport,
                'terminal' => $arivalTerminal,
                'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                'raw_time' => $segment['arrival']['date'] . 'T' . $segment['arrival']['time']
            ],
            'airline' => $airline,
            'cabin' => $cabin,
            'luggage' => implode(',',$baggage),
            'airplane' => $airplane,
            'duration' => $duration,
            'ticket_type' => 'system',
            'flight_number' => $flightNumber,
            'after_flight_stop' => $afterFlight,
            'resBookDesigCode' => $resBookDesigCode,
            'capacity' => $capacity
        ]];

        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[0]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $cabin ?? '',
            'luggage' => '',
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' => $duration,
            'connections' => 0,
            'ticket_type' => 'system',
            'flight_number' => $newLeg->segments[0]['flight_number'],
            'after_flight_stop' => '-',
            'resBookDesigCode' => implode('/', $resBookDesigCodes),
            'capacity' => $capacity,
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = implode('/', $resBookDesigCodes);
        return $newLeg;
    }

    protected function priceMapper($price)
    {
        $price = $price['finalPassengerFare'];
        $newPrice = new stdClass();
        $sumBaseFare = $sumTotalFare = 0;
        foreach ($price as $passengerType => $priceDetails) {
            $pt = ['adult' => 'adults', 'child' => 'children', 'infant' => 'infants'][$passengerType];
            $typeCount =  $this->modifiedPassengersTypeCount[$pt];
            if ($typeCount < 1) {
                continue;
            }
            $tax = $priceDetails['tax'] + $priceDetails['fee'];
            $tf = $tax +  $priceDetails['fare'];

            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['fare'],
                'tax' => $tax,
                'totalFare' => $tf,
                'commission' => 0,
                'payable' => $tf,
                'passengerTypeTotal' => [
                    'baseFare' => $baseFare = $priceDetails['fare'] * $typeCount,
                    'tax' => $tax * $typeCount,
                    'totalFare' => $tf * $typeCount,
                    'commission' => 0,
                    'payable' => $totalFare = $tf * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];
            $sumBaseFare  += $baseFare;
            $sumTotalFare  += $totalFare;
        }
        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $sumBaseFare;
        $newPrice->payable = $sumTotalFare;
        $newPrice->diff = $newPrice->payable - $newPrice->baseFare;

        return $newPrice;
    }

    protected function extractor()
    {

        foreach ($this->rawResult ?? [] as $thisRawResults) {
            if (!empty($thisRawResults['decoded']['outbound'])) {
                $rawResults[] = $thisRawResults['decoded'];
            }
        }
        if (empty($rawResults)) {
            $this->addError('Empty Items');
            return;
        }

        if (!$this->checkCurrency($rawResults)) {
            $this->addError('Currency Mismatch');
            return;
        }
        $this->saveRawResponse();
        $this->extractSupplierCodes();
        $this->isDomesticFlight = $this->isDomestic();
        $this->items = $this->itinerary($this->getRecommendations($rawResults));
    }

    protected function extractSupplierCodes()
    {
        foreach ($this->rawResult as $rawResult) {
            preg_match_all('/"location_code":"(\w+)"/', $rawResult['body'], $airportCodes);
            preg_match_all('/"airline_operating":"(\w+)"/', $rawResult['body'], $oAirlineCodes);
            preg_match_all('/"airline":"(\w+)"/', $rawResult['body'], $mAirlineCodes);
            preg_match_all('/"airplane":"([^"]+)"/', $rawResult['body'], $airplane);
            $totalAirportCodes = array_merge($totalAirportCodes ?? [], $airportCodes[1]);
            $totalAirplaneCodes = array_merge($totalAirplaneCodes ?? [], $airplane[1]);
            $totalAirlineCodes = array_merge($totalAirlineCodes ?? [], $oAirlineCodes[1], $mAirlineCodes[1]);
        }
        $this->airlines = $this->loadAirLines(array_unique($totalAirlineCodes));
        $this->airports = $this->loadAirports(array_unique($totalAirportCodes));
        $this->airplanes = $this->loadAirplanes(array_unique($totalAirplaneCodes));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['AirportCodes' => array_unique(array_merge($departureAirportCodes[1], $arrivalAirportCodes[1])), 'AirlineCode' => array_unique(array_merge($airlineCodes[1], $vAirlineCodes[1], $vAirlineCodes[1])), '$equipments' => $equipments, '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary;
    }

    protected function validatingAirlineCode($itinerary)
    {
        return array_keys($this->airlines)[0] ?? ''; //> the supplier works only for 1 airline so we know there will be only one airline in the result
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['phone', 'text_area', 'email'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'passport_expiry', 'passport_number', 'nationality']
        ];
        $data = $this->addPassengerFormItem($items);

        if ($this->isDomesticFlight) {
            $data = $this->addPassengerFormItem(['all' => ['nationalId']], $data);
        }
        return $data;
    }

    protected function checkCurrency($rawResults)
    {

        $outboundResultCurrency = $rawResults[0]['outbound'][0]['currency_code'] ?? null;
        if ($outboundResultCurrency != $this->seller->currency->abb) {
            lugWarning(__METHOD__, [$rawResults[0]]);
            lugError('Currency Mismatch', ['sellerCurrency' => $this->seller->currency, 'outboundResultCurrency' =>  $outboundResultCurrency ?? null, 'returnResultCurrency' =>  $returnResultCurrency ?? null]);
            return false;
        }
        return true;
    }

    protected function getRecommendations($rawResultsDecoded)
    {
        $mergedFlights = [];
        foreach ($rawResultsDecoded as  $key => $rawResults) {
            if ($this->isOneWaySearch && !empty($rawResults['return'])) {
                lugWarning('return flights exsists for oneway search ! ', [$rawResults]);
                continue;
            } elseif (!$this->isOneWaySearch && empty($rawResults['return'])) {
                lugWarning('return flights is empty for roundtrip search ! ', [$rawResults]);
                continue;
            }
            foreach ($rawResults['outbound'] as $outboundFlight) {
                if (is_array($outboundFlight['return_flights'] ?? '')) {
                    foreach ($rawResults['return'] as $returnFlight) {
                        if (in_array($returnFlight['ref_number'], $outboundFlight['return_flights'])) {
                            $mergedFlights[] = [$outboundFlight, $returnFlight];
                        }
                    }
                } else {
                    $mergedFlights[] = [$outboundFlight];
                }
            }
        }
        return $mergedFlights ?? [];
    }

    protected function supplierRawPricing($itinerary)
    {
        // Initialize sums
        $sums = [
            "adult" => ["fare" => 0, "fee" => 0, "tax" => 0, "additional_fee" => []],
            "child" => ["fare" => 0, "fee" => 0, "tax" => 0, "additional_fee" => []],
            "infant" => ["fare" => 0, "fee" => 0, "tax" => 0, "additional_fee" => []],
        ];

        // Iterate through each leg in the itinerary
        foreach ($itinerary as $key => $leg) {
            foreach (["adult", "child", "infant"] as $passengerType) {
                foreach (["fare", "fee", "tax"] as $attribute) {
                    $sums[$passengerType][$attribute] += $leg["passenger_fare"][$passengerType][$attribute];
                }

                // Update additional_fee (assuming it's an array)
                $sums[$passengerType]["additional_fee"] = array_merge(
                    $sums[$passengerType]["additional_fee"],
                    $leg["passenger_fare"][$passengerType]["additional_fee"]
                );
            }
            $perLegPassengerFare[$key] = $leg['passenger_fare'];
        }

        // Create the final "passenger_fare" array
        $finalPassengerFare = [
            "adult" => $sums["adult"],
            "child" => $sums["child"],
            "infant" => $sums["infant"],
        ];
        return ['finalPassengerFare' => $finalPassengerFare, 'perLegPassengerFare' => $perLegPassengerFare];
    }

    protected function checkFlightSchedule($flightSchedule)
    {
        $this->translatequeryParams = $this->translateQueryParams(true);
        $outboundStatus = false;
        $ReturnStatus = empty($this->translatequeryParams['legs'][1]);
        $originDestinationsDate = $this->generateOriginDestinationDate($this->translatequeryParams['legs'][0]);
        foreach ($originDestinationsDate as $originDestinationDate) {
            if (array_search($originDestinationDate, $flightSchedule) !== false) {
                $outboundStatus = true; // Found the flight
            }
        };

        if (!empty($this->translatequeryParams['legs'][1])) {
            $returnOriginDestinationsDate = $this->generateOriginDestinationDate($this->translatequeryParams['legs'][1]);
            foreach ($returnOriginDestinationsDate as $returnOriginDestinationDate) {
                if (array_search($returnOriginDestinationDate, $flightSchedule) !== false) {
                    $ReturnStatus = true; // Found the flight
                }
            }
        }
        return $outboundStatus && $ReturnStatus;
    }

    protected function changeArrivalTimezone($arrivalCountryCode, $depurtureCountryCode, $arrivalTime)
    {
        $depurtureTimeZone = $this->getTimezones($depurtureCountryCode);
        $arrivalTimeZone = $this->getTimezones($arrivalCountryCode);
        $carbon = Carbon::createFromFormat('H:i:s', $arrivalTime, $depurtureTimeZone[0]->name);
        $newarrivalTime = $carbon->setTimezone($arrivalTimeZone[0]->name);
        $newarrivalTime = $newarrivalTime->format('H:i:s');
        return (count($depurtureTimeZone) > 1 || count($arrivalTimeZone) > 1) ? $arrivalTime : $newarrivalTime;
    }

    private function generateOriginDestinationDate($leg)
    {
        $origin = $leg['origin'];
        $destination = $leg['destination'];
        $departure = $leg['departure'];
        $originDestinationDate = [];

        if ($origin['isCity'] && $destination['isCity']) {
            foreach ($origin['airports'] as $orgAirport) {
                foreach ($destination['airports'] as $desAirport) {
                    $originDestinationDate[] = $orgAirport . '_' . $desAirport . '_' . $departure;
                }
            }
        } elseif ($origin['isCity']) {
            foreach ($origin['airports'] as $orgAirport) {
                $originDestinationDate[] = $orgAirport .  '_' . $destination['abb'] . '_' . $departure;
            }
        } elseif ($destination['isCity']) {
            foreach ($destination['airports'] as $desAirport) {
                $originDestinationDate[] = $origin['abb'] . '_' . $desAirport . '_' . $departure;
            }
        } else {
            $originDestinationDate[] = $origin['abb'] .  '_' . $destination['abb'] . '_' . $departure;
        }

        return $originDestinationDate;
    }
}

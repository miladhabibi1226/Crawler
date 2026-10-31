<?php

namespace App\TravelServices\Suppliers\Sabre\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use Carbon\Carbon;
use stdClass;

trait Search
{
    use FlightSearch;
    protected $fareComponents, $segmentNumber = 0;
    public function canBedone()
    {
        $this->authorizeRequest();
        if (!$this->translatedQueryParams = $this->translateQueryParams()) {
            return $this->addError('Query params translation failed');
        }
    }

    public function requestGenerator()
    {
        // $this->LugTheResponse = 1;
        $depends = $this->translatedQueryParams + $this->seller->api->configs;
        // lugInfo("Sabre Search Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($depends['url']) . "/v4/offers/shop",
            'timeout' => 25,
            'apiMethod' => 'Search'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $rawResult = $this->rawResult['decoded']['groupedItineraryResponse'] ?? [];
        if (empty($rawResult['statistics']['itineraryCount'])) {
            lugError('Sabre NoResult', ['rawResult' => $this->rawResult]);
            $this->addError('NoResult');
            return $this->items = [];
        }
        $this->extractSupplierCodes();
        $this->saveRawResponse();
        $this->items = $this->itinerary($this->reformItineraries($rawResult));
    }

    protected function originDestinationOption($itinerary)
    {
        $this->fareComponents = $itinerary['fareComponentSegments'];
        $this->segmentNumber = 0;
        return $itinerary['legs'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['pricingInformation']['validatingCarrierCode'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['pricingInformation'];
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = true;
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = ['revalidateFlight' => false];
        $info->extra = [
            'fareRulesRequestData' => $itinerary['fareRulesInfo']
        ];
        return $info;
    }

    protected function reformItineraries($rawResult)
    {
        $availablePassengers = array_filter($this->modifiedPassengersTypeCount, fn($item) => $item > 0);
        $this->availablePassengers = array_values(array_keys($availablePassengers));
        $itineraryGroups = $rawResult['itineraryGroups'];
        $this->legs = collect($rawResult['legDescs']);
        $this->segments = collect($rawResult['scheduleDescs']);
        $this->baggageAllowanceDescs = collect($rawResult['baggageAllowanceDescs']);
        $this->fareComponents = collect($rawResult['fareComponentDescs']);
        $itineraries = [];
        $rawCount = $skippedForSegmentMismatch = $skippedForEticketable = 0;
        foreach ($itineraryGroups as $group) {
            foreach ($group['itineraries'] as $itinerary) {
                $rawCount++;
                $fareComponents = $this->getItineraryFareComponents($itinerary['pricingInformation']);
                if (empty($fareComponents)) {
                    $skippedForSegmentMismatch++;
                    $skippedForSegmentMismatchData[] = $itinerary;
                    continue;
                }
                foreach ($fareComponents as $g => $fareComponent) {
                    $legs = $this->getItineraryLegs($itinerary, $group['groupDescription'], $fareComponent['baggages']);
                    $segCount = array_sum(array_map(fn($item) => count($item['segments']), $legs));
                    if ($segCount !== count($fareComponent['segments'])) {
                        lugError('seg total Count mismatch', ['itinerary' => $itinerary, 'g' => $rawCount, 'segCount' => $segCount, 'legs' => $legs, 'fareComponents' => $fareComponent]);
                        $this->callException('seg total Count mismatch');
                    }
                    $itineraries[] = [
                        'legs' => $legs,
                        'pricingInformation' => $fareComponent['pricingInformation'],
                        'fareRulesInfo' => $fareComponent['fareRulesInfo'],
                        'fareComponentSegments' => $fareComponent['segments'],
                        'eTicketable'  => $fareComponent['pricingInformation']['eTicketable'] ?? false,
                    ];
                }
            }
        }
        if ($skippedForSegmentMismatch > 1) {
            lugWarning("$rawCount raw. $skippedForSegmentMismatch cabin/psngrsCnt/segs skips. $skippedForEticketable Eticketable skips", ['skippedForEticketableData' => $skippedForEticketableData ?? [], 'skippedForSegmentMismatchData' => $skippedForSegmentMismatchData ?? []]);
        }
        return $itineraries;
    }

    protected function getItineraryFareComponents($pricingInformation)
    {
        foreach ($pricingInformation as $index => $legPricingInformation) {
            $segs = $bags = $components = [];
            ///> skipping soldOut brands
            if (!empty($legPricingInformation['soldOut']) || !($legPricingInformation['fare']['eTicketable'] ?? false)) continue;

            foreach ($legPricingInformation['fare']['passengerInfoList'] as $psngrInfoKey => $passengersPricings) {
                foreach ($passengersPricings['passengerInfo']['fareComponents'] as $fareComponent) {
                    $comp = $this->fareComponents->where('id', $fareComponent['ref'])->first();
                    $passengerType = $passengersPricings['passengerInfo']['passengerType'];
                    if (in_array($legPricingInformation['fare']['validatingCarrierCode'], ['PC', 'FZ'])) {
                        ///> these are LLC airlines and they might send an extra adult instead of child or infant, and according to their team we can be sure that if we get ADT-ADT-INF it means ADT-CHD-INF
                        $passengerType = ['adults' => 'ADT', 'children' => 'CNN', 'infants' => 'INF'][$this->availablePassengers[$psngrInfoKey]];
                    }

                    ///> skipping brands that have unrequested cabin
                    $cabin = ['P' => 'Premium First', 'F' => 'First', 'J' => 'Premium Business', 'C' => 'Business', 'S' => 'PremiumEconomy', 'Y' => 'Economy'][$comp['cabinCode'] ?? ''] ?? '';
                    if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) continue 2; ///> this way we'll include Premium options in the results

                    $brand = $comp['brand']['code'] ?? $index;
                    foreach ($fareComponent['segments'] as $segment) {
                        if (empty($segment['segment'])) continue; ///> sometimes there are more than one segments and one doesn't have a 'segment' item but a 'surface' item which is empty (those that i have seen so far). idk what it is but i'm skipping it
                        $seg = $segment['segment'];
                        $seg['beginAirport'] = $fareComponent['beginAirport'];
                        $seg['endAirport'] = $fareComponent['endAirport'];
                        $seg['brand'] = $brand;
                        $segs[$index][$passengerType][] = $seg;
                    }

                    $components[$passengerType][] = [
                        'fareBasisCode' => $comp['fareBasisCode'],
                        'governingCarrier' => $comp['governingCarrier'],
                        'beginAirport' => $fareComponent['beginAirport'],
                        'endAirport' => $fareComponent['endAirport'],
                        'directionality' => $comp['directionality']
                    ];
                }
                foreach ($passengersPricings['passengerInfo']['baggageInformation'] ?? [] as $legIndex => $legBaggages) {
                    foreach ($legBaggages['segments'] as $segIndex) {
                        $bags[$segIndex['id']][$passengerType] = $this->baggageAllowanceDescs->where('id', $legBaggages['allowance']['ref'])->first();
                    }
                }
            }

            ///> skipping brands that are missing any of the requested passenger types
            if (empty($bags) || empty($segs) || (empty($segs[$index]['ADT']) !== empty($this->modifiedPassengersTypeCount['adults'])) || (empty($segs[$index]['CNN']) !== empty($this->modifiedPassengersTypeCount['children'])) || (empty($segs[$index]['INF']) !== empty($this->modifiedPassengersTypeCount['infants']))) continue;

            ///checking to make sure all the segments for all the passengers are the same (the codes might be different)
            foreach ($segs as $segInfo) {
                $passengerSegsInfo = array_values($segInfo);
                foreach ($passengerSegsInfo as $k => $passengerSegs) {
                    if ($passengerSegs != ($passengerSegsInfo[$k - 1] ?? $passengerSegs)) continue 2;
                }
            }
            $ItineraryFareComponents[] = [
                'pricingInformation' => $legPricingInformation['fare'],
                'fareRulesInfo' => $components,
                'baggages' => $bags,
                'segments' => $segs[$index]['ADT']
            ];
        }
        return $ItineraryFareComponents ?? [];
    }

    protected function getItineraryLegs($itinerary, $groupDescription, $baggages)
    {
        $legRefs = array_column($itinerary['legs'], 'ref');
        $itinararyLegs = [];
        $baggageSegmentCounter = 0; ///> we have to use normal counter bc there is no other way but i'm kinda sure that the indexes are with order of the actual segments of the legs
        foreach ($legRefs as $key => $legNumber) {
            $leg = $this->legs->where('id', $legNumber)->first();
            $segmentRefs = $leg['schedules'];
            $legSegments = [];
            foreach ($segmentRefs as $segmentkey => $SegmentInfo) {
                $segment = $this->segments->where('id', $SegmentInfo['ref'])->first();
                $legSegments[$segmentkey] = $segment;
                $legSegments[$segmentkey]['departureDateTime'] = $this->getSegmentDateTime($legSegments[$segmentkey]['departure']['time'], $groupDescription['legDescriptions'][$key]['departureDate'], $legSegments[$segmentkey]['departure']['dateAdjustment'] ?? ($SegmentInfo['departureDateAdjustment'] ?? 0));
                $legSegments[$segmentkey]['arrivalDateTime'] = $this->getSegmentDateTime($legSegments[$segmentkey]['arrival']['time'], $groupDescription['legDescriptions'][$key]['departureDate'], $legSegments[$segmentkey]['arrival']['dateAdjustment'] ?? ($SegmentInfo['departureDateAdjustment'] ?? 0));
                $legSegments[$segmentkey]['baggages'] = $baggages[$baggageSegmentCounter] ?? [];
                $baggageSegmentCounter++;
            }

            $itinararyLegs[] = [
                'info' => array_merge($leg, $groupDescription['legDescriptions'][$key]),
                'direction' => $key,
                'segments' => $legSegments
            ];
        }
        return $itinararyLegs;
    }

    protected function legMapper($leg, $legIndex)
    {
        $segments = $leg['segments'];
        $newLeg = new stdClass();
        $newLeg->airlines = $newLeg->segments = $legFlightNumbers = [];
        $newLeg->stops = count($segments) - 1;
        foreach ($segments as $key => $segment) {
            $fareInfo = $this->fareComponents[$this->segmentNumber];
            $marketingAirline = $this->airlines[$segment['carrier']['marketing']] ?? null;
            if (empty($marketingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['carrier']['marketing'],
                    'data_reference' => types('supplierDataReference', 'Sabre'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }
            $operatingAirline = $this->airlines[$segment['carrier']['operating']] ?? null;
            if (empty($operatingAirline)) {
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['carrier']['operating'],
                    'data_reference' => types('supplierDataReference', 'Sabre'),
                ];
                return false;
            }
            $cabin = ['P' => 'Premium First', 'F' => 'First', 'J' => 'Premium Business', 'C' => 'Business', 'S' => 'PremiumEconomy', 'Y' => 'Economy'][$fareInfo['cabinCode'] ?? ''] ?? '';
            if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) { ///> this way we'll include Premium options in the results
                // lugWarning(
                //     'cabin mismatch '.strtoupper($cabin),
                //     ['request' => $this->queryParams['cabin'], 'result' => $cabin, 'segment' => $segment, 'fareInfo' => $fareInfo]
                // );
                return false;
            }
            $durationByMinutes = $segment['elapsedTime'];
            $departureCity = $this->airports[$segment['departure']['airport']]['city']['title'] ?? $segment['departure']['city'];
            $departureAirport = $this->airports[$segment['departure']['airport']] ?? $segment['departure']['airport'];
            $departureTerminal = $segment['departure']['terminal'] ?? '-';
            $departureDateTime = $this->humanDateTime($segment['departureDateTime']);

            $arrivalCity = $this->airports[$segment['arrival']['airport']]['city']['title'] ?? $segment['arrival']['city'];
            $arrivalAirport = $this->airports[$segment['arrival']['airport']] ?? $segment['arrival']['airport'];
            $arrivalTerminal = $segment['arrival']['terminal'] ?? '-';
            $arrivalDateTime = $this->humanDateTime($segment['arrivalDateTime']);

            $baggage = $this->baggageModifier($segment);
            $airplane = $this->airplanes[$segment['carrier']['equipment']['code']]['title'] ?? $segment['carrier']['equipment']['code'];
            $duration = intval($durationByMinutes / 60) . ":" . ($durationByMinutes % 60);
            $legFlightNumbers[] = $flightNumber = $segment['carrier']['marketingFlightNumber'];
            $afterFlight = $this->between2Time(
                $segment['arrivalDateTime'],
                $segments[$key + 1]['departureDateTime'] ?? $segment['arrivalDateTime']
            );
            $newLeg->airlines[] = $marketingAirline['id'];
            $resBookDesigCodes[] = $resBookDesigCode = $fareInfo['bookingCode'] . (is_int($fareInfo['brand']) ? '' : '-' . $fareInfo['brand']);
            $capacities[] = $capacity = $fareInfo['seatsAvailable'];
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
                    'terminal' => $arrivalTerminal,
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
                'capacity' => $capacity,
                'brand' => $fareInfo['brand']
            ];
            $this->segmentNumber++;
        }
        $legDurationByMinutes = $leg['info']['elapsedTime'];
        $legDuration = intval($legDurationByMinutes / 60) . ":" . ($legDurationByMinutes % 60);
        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[count($newLeg->segments) - 1]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $cabin,
            'luggage' => $baggage,
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' => $legDuration,
            'connections' => count($segments) - 1,
            'ticket_type' => 'system',
            'flight_number' => implode('/', $legFlightNumbers),
            'resBookDesigCode' => implode('/', $resBookDesigCodes),
            'after_flight_stop' => '-',
            'capacity' => empty($capacities) ? '-' : min($capacities)
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = implode('/', $resBookDesigCodes);
        return $newLeg;
    }

    protected function priceMapper($price)
    {
        ///> in LLC airlines' flights sometimes when there is no specific fare for child the'll sed the adult object instead, or when there is no inf they'll send child/adt
        if ($price['totalFare']['currency']   != $this->seller->currency->abb) {
            lugError('Currency mismatch', ['seller price' => $this->seller->currency->abb, 'result price' => $price['totalFare']['currency']]);
            $this->callException('Currency mismatch');
        }
        $newPrice = new stdClass();
        if (!$this->checkResultPassengersCoun($price)) {
            return false;
        }
        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;
        $breakDowns = $price['passengerInfoList'];
        foreach ($breakDowns as $key => $breakDown) {
            $priceDetails = $breakDown['passengerInfo'];
            $passengerType = ['ADT' => 'adult', 'CNN' => 'child', 'INF' => 'infant'][$priceDetails['passengerType']];
            if (in_array($price['validatingCarrierCode'], ['PC', 'FZ'])) {
                ///> these are LLC airlines and they might send an extra adult instead of child or infant, and according to their team we can be sure that if we get ADT-ADT-INF it means ADT-CHD-INF
                $passengerType = ['adults' => 'adult', 'children' => 'child', 'infants' => 'infant'][$this->availablePassengers[$key]];
            }
            $typeCount = $priceDetails['passengerNumber'];
            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                lugError($passengerType . ' count mismatch', ['requestedPassengers' => $this->modifiedPassengersTypeCount, 'Count' => ${$passengerType . 'Count'}, 'typeCount' => $typeCount, 'breakDowns' => $breakDowns]);
                return false;
            } else {
                ///> to check later that all the requested passengers types are available in th eresult
                ${$passengerType . 'Count'} = 'exists';
            }

            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $bf = $priceDetails['passengerTotalFare']['equivalentAmount'],
                'tax' => $tx = $priceDetails['passengerTotalFare']['totalTaxAmount'],
                'totalFare' => $tf = $bf + $tx,
                'commission' => $cm = $priceDetails['passengerTotalFare']['equivalentAmount'] - $priceDetails['passengerTotalFare']['totalFare'],
                'payable' => $pb = $priceDetails['passengerTotalFare']['totalFare'],
                'passengerTypeTotal' => [
                    'baseFare' => $bf * $typeCount,
                    'tax' => $tx * $typeCount,
                    'totalFare' => $tf * $typeCount,
                    'commission' => $cm * $typeCount,
                    'payable' => $pb * $typeCount,
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
        $newPrice->baseFare = $price['totalFare']['equivalentAmount'];
        $newPrice->payable = $price['totalFare']['totalPrice'];
        $newPrice->diff = $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function getSegmentDateTime($time, $date, $departureDateAdjustment)
    {
        $date = Carbon::parse($date);
        if (!empty($departureDateAdjustment)) {
            $date->addDays($departureDateAdjustment);
        }
        preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}/', $date->format("Y-m-d") . "T$time", $output_array);
        return $output_array[0];
    }

    protected function baggageModifier($segment)
    {
        $text = '';
        foreach ($segment['baggages'] as $passengerType => $allowance) {
            if (key_exists('pieceCount', $allowance)) {
                $text = "$text {$allowance['pieceCount']} PC/$passengerType -";
            } elseif (key_exists('weight', $allowance)) {
                $text = "$text {$allowance['weight']} {$allowance['unit']}/$passengerType -";
            }
        }
        return rtrim(ltrim($text), '-');
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['phone', 'email', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality', 'passport_expiry', 'passport_number', 'passport_country']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function extractSupplierCodes()
    {
        preg_match_all('/"airport":"(\w+)"/', $this->rawResult['body'], $airportCodes);
        preg_match_all('/"city":"(\w+)"/', $this->rawResult['body'], $cityCodes);
        preg_match_all('/"operating":"(\w+)"/', $this->rawResult['body'], $airlineCodes);
        preg_match_all('/"marketing":"(\w+)"/', $this->rawResult['body'], $airlineCodesmarketing);
        preg_match_all('/"validatingCarrierCode":"(\w+)"/', $this->rawResult['body'], $airlineCodesvalidating);
        preg_match_all('/"equipment":(.*?)"code":"(\w+)"/', $this->rawResult['body'], $equipments);
        $this->airlines = $this->loadAirLines(array_unique(array_merge($airlineCodes[1], $airlineCodesmarketing[1], $airlineCodesvalidating[1])));
        $this->airports = $this->loadAirports(array_unique($airportCodes[1]));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments[2]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult[body]' => $this->rawResult['body'], 'airlines' => array_unique(array_merge($airlineCodes[1], $airlineCodesmarketing[1])), 'airports' => array_unique($airportCodes[1]), 'airplanes' => array_unique($equipments[2]), '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getFields($auth)
    {
        foreach ($this->translatedQueryParams['legs'] as $legIndex => $leg) {
            $OriginDestinationInformation[] = [
                "DepartureDateTime" => $leg['departure'] . 'T00:00:00',
                "DestinationLocation" => ["LocationCode" => $leg['destination']['abb']],
                "OriginLocation" => ["LocationCode" => $leg['origin']['abb']],
                "RPH" => (string) $legIndex
            ];
        }
        $fields = [
            "OTA_AirLowFareSearchRQ" => [
                "AvailableFlightsOnly" => true,
                "OriginDestinationInformation" => $OriginDestinationInformation,
                "POS" => [
                    "Source" => [
                        [
                            "PseudoCityCode" => $auth['pcc'],
                            "RequestorID" => [
                                "CompanyName" => [
                                    "Code" => "TN"
                                ],
                                "ID" => "1",
                                "Type" => "1"
                            ]
                        ]
                    ]
                ],
                "TPA_Extensions" => [
                    "IntelliSellTransaction" => [
                        "RequestType" => [
                            "Name" => "200ITINS"
                        ]
                    ]
                ],
                "TravelPreferences" => [
                    "TPA_Extensions" => [
                        "DataSources" => [
                            "ATPCO" => "Enable",
                            "LCC" => "Disable",
                            "NDC" => "Disable"
                        ]
                    ]
                ],
                "TravelerInfoSummary" => [
                    "AirTravelerAvail" => [
                        [
                            "PassengerTypeQuantity" => [
                                [
                                    "Code" => "ADT",
                                    "Quantity" => (int) $this->translatedQueryParams['adults']
                                ]
                                ///> chilren and infants are added later because 0 is not accepted
                            ]
                        ]
                    ],
                    "SeatsRequested" => [
                        (int) ($this->translatedQueryParams['adults'] + $this->translatedQueryParams['children'])
                    ],
                    "PriceRequestInformation" => [
                        "TPA_Extensions" => [
                            "BrandedFareIndicators" => [
                                "SingleBrandedFare" => false,
                                "MultipleBrandedFares" => true,
                                "ReturnBrandAncillaries" => true
                            ]
                        ]
                    ]
                ],
                "Version" => "3"
            ]
        ];
        if (!empty($this->translatedQueryParams['children'])) {
            $fields['OTA_AirLowFareSearchRQ']['TravelerInfoSummary']['AirTravelerAvail'][0]['PassengerTypeQuantity'][] = [
                "Code" => "CNN", /// for sabre it's either CNN or C09 where 09 is the age of the child
                "Quantity" => (int) $this->translatedQueryParams['children']
            ];
        }
        if (!empty($this->translatedQueryParams['infants'])) {
            $fields['OTA_AirLowFareSearchRQ']['TravelerInfoSummary']['AirTravelerAvail'][0]['PassengerTypeQuantity'][] = [
                "Code" => "INF",
                "Quantity" => (int) $this->translatedQueryParams['infants']
            ];
        }
        return $fields;
    }

    protected function checkResultPassengersCoun($price)
    {
        $requestedPassengers[] = 'ADT-' . ($this->modifiedPassengersTypeCount['adults'] ?? 0);
        if (!empty($this->modifiedPassengersTypeCount['children'])) {
            $requestedPassengers[] = '(CNN|ADT)-' . ($this->modifiedPassengersTypeCount['children'] ?? 0);
        }
        if (!empty($this->modifiedPassengersTypeCount['infants'])) {
            $requestedPassengers[] = '(INF|CHD)-' . ($this->modifiedPassengersTypeCount['infants'] ?? 0);
        }
        foreach ($price['passengerInfoList'] as $key => $breakDown) {
            $bdDetails[$key] = "{$breakDown['passengerInfo']['passengerType']}-{$breakDown['passengerInfo']['passengerNumber']}";
        }
        $bdDetailsText = implode('.', $bdDetails);
        $requestedPassengersText = implode('.', $requestedPassengers);
        if (!preg_match("/$requestedPassengersText/", $bdDetailsText, $output_array)) {
            lugError('Passengers count mismatch', ['modifiedPassengersTypeCount' => $this->modifiedPassengersTypeCount, 'result count' => $bdDetails]);
            return false;
        }
        return true;
    }
}

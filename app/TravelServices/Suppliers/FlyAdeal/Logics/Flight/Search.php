<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics\Flight;

use Carbon\Carbon;
use stdClass;
use App\TravelServices\Suppliers\Navitaire\Logics\Flight\Search as NavitaireSearch;

trait Search
{
    use NavitaireSearch;

    protected $latestSession, $exchangeData, $translatequeryParams;
    public function canBedone()
    {
        if ($this->queryParams['cabin'] == 'first') {
            return $this->addError('first class flights are unavailable');
        }
        $this->authorizeRequest();
        if (empty($this->supplierSession?->session)) {
            return $this->addError('Can not get session');
        }
        if (!$this->translatedQueryParams = $this->translateQueryParams(true)) {
            return $this->addError('Query params translation failed');
        }
    }

    protected function getSearchUrl()
    {
        return '/api/nsk/v1/availability/search/ssr';
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded']['data']['results'] ?? false;
        if (empty($rawResults[0]['trips']) || count($rawResults) != count($this->queryParams['legs'])) {
            $this->addError('Empty results');
            return;
        }
        $this->extractSupplierCodes();
        $this->saveRawResponse();

        ///> in FlyAdeal unlike aljazeera we don't need to exchange, if we send the seller code they'll return the prices in seller price. we still call findExchangeRate for codes and data to be like navitaire
        $this->findExchangeRate();
        $responseCurrency = $this->getResultCurrency();
        if (strtoupper($responseCurrency) != strtoupper($this->seller->currency->abb)) {
            lugError('currency problems', [$responseCurrency, $this->seller->currency->abb, $this->rawResult]);
            return $this->addError('currency problemms');
        }

        if (empty($this->exchangeData)) {
            ///> means the api failed. we'll fail the process bc we can't calculate amount
            return $this->addError('cound not get exchange rate');
        }
        $this->isDomesticFlight = $this->isDomestic('Saudi Arabia');
        $this->resultCurrency = $this->rawResult['decoded']['data']['currencyCode'];

        $this->items = $this->itinerary($this->getPriceQuote($this->recoomendations($rawResults)));
    }

    protected function recoomendations($rawResults)
    {
        ///> this probably won't work for multi-destination search
        $faresAvailabile = collect($this->rawResult['decoded']['data']['faresAvailable']);
        $bundleOffers = $this->rawResult['decoded']['data']['bundleOffers'];
        ///> the legs come seperately, each leg has multiple items that we want to combine
        foreach ($rawResults as $legIndex => $legResults) {
            if (empty($legResults['trips'])) {
                return [];
            }
            if (count($legResults['trips']) > 1) {
                lugWarning("unexpected. probably bc of multiAirportCity", [$rawResults]);
                return [];
            }
            $journeysAvailableByMarkets = $legResults['trips'][0]['journeysAvailableByMarket'];
            foreach ($journeysAvailableByMarkets as $journeyIndex => $journeysAvailableByMarket) {
                foreach ($journeysAvailableByMarket as $itemKey => $item) {
                    $allLegs[$legIndex][] = ["$legIndex.$journeyIndex-$itemKey"];
                }
            }
        }

        $combinations = array_shift($allLegs);
        foreach ($allLegs as $legIndex => $legItems) {
            $temp = [];
            foreach ($combinations as $item) {
                foreach ($legItems as $legItem) {
                    $temp[] = array_merge($item, $legItem);
                }
            }
            $combinations = $temp;
        }
        $firstCombinations = [];

        $skippedForDepartureTime = 0;
        foreach ($combinations as $combination) {
            $finalCombo = [];
            foreach ($combination as $legItem) {
                list($legIndex, $itemIndex) = explode('-', $legItem);
                list($legIndex, $journeyIndex) = explode('.', $legIndex);
                $finalCombo[$legIndex] = $rawResults[$legIndex]['trips'][0]['journeysAvailableByMarket'][$journeyIndex][$itemIndex];
            }
            if (!$this->isOneWaySearch) {
                if (empty($finalCombo[1])) {
                    lugWarning('does not have the second leg', [$finalCombo, $combinations, $allLegs]);
                    continue;
                }
                $leg1Arrival = Carbon::create($finalCombo[0]['designator']['arrival']);
                $leg2Departure = Carbon::create($finalCombo[1]['designator']['departure']);
                if ($leg2Departure <= $leg1Arrival || $leg1Arrival->diffInMinutes($leg2Departure) < 90) {
                    // lugError('Less than 90 mins between 2 flights', ['$leg1Arrival' => $leg1Arrival, '$leg2Departure' => $leg2Departure, 'diffInMinutes' => $leg1Arrival->diffInMinutes($leg2Departure)]);
                    $skippedForDepartureTime++;
                    continue;
                }
            }
            $firstCombinations[] = $finalCombo;
        }
        ///> now each item has different fares and each has to become a combination
        $finalCombinations = [];
        foreach ($firstCombinations as $k => $combination) {
            $combinationTemp = [];

            foreach ($combination as $legIndex => $leg) {
                foreach ($leg['fares'] as $fareIndex => $fare) {
                    foreach ($fare['details'] as $segIndex => $fareDetail) {
                        if ($fareDetail['status'] != '1') {
                            unset($combinationTemp[$legIndex][$fareIndex]);
                            continue 2;
                        }
                        foreach ($fareDetail['bundleReferences'] as $bundleIndex => $bundle) {
                            $combinationTemp[$legIndex]["$fareIndex.$bundleIndex"][$segIndex] = "{$fare['fareAvailabilityKey']}.{$fareDetail['reference']}.$bundle.$fareIndex";
                        }
                    }
                }
            }

            ///> considering we'll only have maximum 2 legs. mo multidestination
            $FareCombinations = [];
            foreach ($combinationTemp[0] ?? [] as $firstLegFares) {
                $temp = [$firstLegFares];
                if (empty($combinationTemp[1])) {
                    $FareCombinations[] = [$firstLegFares];
                } else {
                    foreach ($combinationTemp[1] ?? [] as $secondLegFares) {
                        $FareCombinations[] = array_merge([$firstLegFares], [$secondLegFares]);
                    }
                }
            }

            foreach ($FareCombinations as $FareCombination) {
                $finalCombination = $bundles = [];
                foreach ($FareCombination as $legKey => $segments) {
                    $bundles = [];
                    $leg = $combination[$legKey];
                    foreach ($segments as $segKey => $info) {
                        $info = explode('.', $info);
                        $fareAvailabilityKey = $info[0];
                        $detailReference = $info[1];
                        $bundle = $info[2];
                        $fareKey = $info[3];

                        $fareAvailability = $faresAvailabile->Where('fareAvailabilityKey', $fareAvailabilityKey)->first(); ///> fareAvailabilityKey is the same for all segments
                        $faresCollection = collect($fareAvailability['fares']);
                        $details = $faresCollection->Where('reference', $detailReference)->first();
                        $leg['segments'][$segKey]['details'] = $leg['fares'][$fareKey]['details'][$segKey];
                        $leg['segments'][$segKey]['details']['extraInfo'] = $details;
                        $leg['segments'][$segKey]['bundleInfo'] = $bundleOffers[$bundle];
                        $fareAvailability['bundle'] = $bundleOffers[$bundle];
                        $fareAvailability['journeyKey'] = $combination[$legKey]['journeyKey']; ///> to be used in priceMapper
                        $bundles[] = $bundleOffers[$bundle]['bundleCode'];
                    }
                    if (count(array_unique($bundles)) != 1) {
                        lugWarning('skipping resul bc bundles are different for dif segs', [$leg, $bundles]);
                        continue 2;
                    }
                    unset($leg['fares']);

                    $finalCombination['legs'][$legKey] = $leg;
                    $finalCombination['fare']['availability'][$legKey] = $fareAvailability;
                    $finalCombination['bookingData'][$legKey] = [
                        'origin' => $combination[$legKey]['designator']['origin'],
                        'destination' => $combination[$legKey]['designator']['destination'],
                        'journeyKey' => $combination[$legKey]['journeyKey'],
                        'fareAvailabilityKey' => $fareAvailability['fareAvailabilityKey'],
                        'bundleCode' => array_unique($bundles)[0] ///> all segments of each leg have to have the same bundle. we have made sure of it before
                    ];
                }
                $finalCombinations[] = $finalCombination;
            }
        }
        // lugWarning(__METHOD__, [$finalCombinations[0], $finalCombinations[1], $finalCombinations[2]]);
        return $finalCombinations;
    }

    protected function getPriceQuote($recoomendations) ///> re-written in custom search
    {
        ///> for fluadeal, the price that we receive in get availability is not the final price. it does not includes infant price and some additional taxes that only get added in priceQuote api
        ///> so we'll call priceQuote to get the final price that
        $this->multi = true;
        $auth = $this->seller->api->configs;
        $this->call = [];
        ///> priceQuotes is for each journyKey/fareKey combination
        ///> since we might have multiple recomendation for each journyKey/fareKey combination, and we want to avoide calling the same request for all recomendation, we need to make sure we call priceQuote once for each combination
        foreach ($recoomendations as $recoomendation) {
            $keys = $this->combineHourneyKeys($recoomendation);
            if (empty($this->call[$keys])) {
                $depends = $recoomendation + $auth;
                $this->call[$keys] = [
                    'fields' => json_encode($this->getBCFields($depends)),
                    'url' =>  $this->cleanUrlSlash($auth['url']) . "/api/nsk/v2/bookings/quote",
                    'apiMethod' => 'BookingQuote'
                ];
            }
        }
        $this->action();
        return $this->addPriceQuoteTorecomendations($recoomendations ?? []);
    }

    protected function addPriceQuoteTorecomendations($recoomendations)
    {
        $skipped = 0;
        foreach ($recoomendations as $recKey => $recoomendation) {
            $keys = $this->combineHourneyKeys($recoomendation);
            $result = json_decode($this->rawResult[$keys]['body'], true);
            if (empty($result['data']['breakdown'])) {
                $skipped++;
                unset($recoomendations[$recKey]);
                continue;
            }
            $recoomendations[$recKey]['fare']['priceQuote'] = $result['data']['breakdown'] ?? [];
        }
        if ($skipped > 0) {
            lugWarning("could not get the price results for $skipped items");
        }
        $this->rawResult = $this->call = [];
        return $recoomendations ?? [];
    }

    protected function combineHourneyKeys($recoomendation)
    {
        $keys = [];
        foreach ($recoomendation['bookingData'] as $data) {
            $keys[] = $data['journeyKey'];
            $keys[] = $data['fareAvailabilityKey'];
        }
        return implode(';;;', $keys);
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = false; //> can't onHold bc navitaire doesn't have tickets and once we get the pnr the book is finished
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = ['tripSell' => false];
        $info->extra = [
            'isDomesticFlight' => $this->isDomesticFlight,
            'resultCurrency' => $this->resultCurrency,
            'bookingData' => $itinerary['bookingData']
        ];
        return $info;
    }

    protected function priceMapper($source)
    {
        $newPrice = new stdClass();

        ///> here in $source we have both availability and priceQuote results
        ///> in availability result, the prices for each leg comes seperately. infants' price don't come at all and there are some taxes that aren't added yet
        ///> in priceQuote result we have the legs total price per adult (including the extra taxes), and infant prices seperately
        ///> in some other part of the response there is a per passenger price but the taxes' calculation are weired there, so the calculations might end up wrong. so that part of the response is not here in $source
        ///> we'll use priceQuote result in $source considering adult and child prices are the same. so we'll multiply leg price by (adultCount+childCount), and add total infs price and then sum it all up
        ///> the sum has to be the same amount as balanceDue
        ///> the bundle Prices exist in the availability result. they need to be added for each psngr (adt and chd) for each journey, and the be added to the balanceDue
        ///> as far as i have seen adt and chd's bundle prices are the same, but'll we'll double check and skip the result if they're not (bc the code we are writing considers that adt and chd prices are completely the same)

        ///> combining all legs' prices which is for 1ADT only
        $availabilityResult = collect($source['availability']);
        foreach ($source['priceQuote']['journeys'] as $priceDetails) {

            ///> calculating bundle price that needs to be added to each ADT's price
            $availabilityPrice = $availabilityResult->where('journeyKey', $priceDetails['journeyKey'])->first();

            ///> if bundle price is 0, there is gonna be one object in bundlePrices with passengerType value of null
            ///> if the price is not 0, there is gonna be 1 object for each passenger type. (there is an object for inf too but we'll ignore bc the support team said bundles are not given to infants. "Ignore that as our infant is not a passenger type but an SSR.")
            ///> we'll be also making sure the price for adt and chd are the same (bc the code we are writing considers that adt and chd prices are completely the same)
            $comparables = [];
            foreach ($availabilityPrice['bundle']['bundlePrices'] as $passengerBundlePrice) {
                if ($passengerBundlePrice['passengerType'] == 'INF') continue;
                $comparables[] = $passengerBundlePrice['totalPrice'];
            }
            if (count(array_unique($comparables)) != 1) {
                lugWarning('adt and chd bundlePrices are different', [$source]);
                return false;
            }
            $bundlePrice = array_unique($comparables)[0];

            ///> $legPrices is gonna be sum of all legs' prices which is for 1ADT only
            $legPrices = [
                'baseFare' => ($legPrices['baseFare'] ?? 0) + $priceDetails['totalAmount'],
                'tax' => ($legPrices['tax'] ?? 0) + $priceDetails['totalTax'],
                'bundle' => ($legPrices['bundle'] ?? 0) + $bundlePrice,
                'totalFare' => ($legPrices['totalFare'] ?? 0) + $priceDetails['totalTax'] + $bundlePrice + $priceDetails['totalAmount']
            ];
        }

        foreach ($this->modifiedPassengersTypeCount as $type => $typeCount) {
            if ($typeCount == 0) continue;
            $passengerType = ['adults' => 'adult', 'children' => 'child', 'infants' => 'infant'][$type];

            if ($passengerType == 'infant') {
                $infant = $source['priceQuote']['passengerTotals']['infant']['total'];
                $infanttaxes = $source['priceQuote']['passengerTotals']['infant']['taxes'] ?? 0;
                $newPriceBreakDowns[$passengerType] = [
                    'baseFare' => $bf = $infant / $typeCount,
                    'tax' => $tx = $infanttaxes / $typeCount,
                    'totalFare' => $bf + ($infanttaxes / $typeCount),
                    'commission' => 0,
                    'payable' => $bf + ($infanttaxes / $typeCount),
                    'passengerTypeTotal' => [
                        'baseFare' => $tbf = $infant,
                        'tax' => $infanttaxes,
                        'totalFare' => $tbf + $tx,
                        'commission' => 0,
                        'payable' => $tpb = $tbf + $tx,
                        'passengersCount' => $typeCount
                    ]
                ];
            } else {
                $newPriceBreakDowns[$passengerType] = [
                    'baseFare' => $bf = $legPrices['baseFare'],
                    'tax' => ($tx = $legPrices['tax']) + $legPrices['bundle'],
                    'totalFare' => $legPrices['totalFare'],
                    'commission' => 0,
                    'payable' => $legPrices['totalFare'],
                    'passengerTypeTotal' => [
                        'baseFare' => $tbf = $legPrices['baseFare'] * $typeCount,
                        'tax' => ($legPrices['tax'] + $legPrices['bundle']) * $typeCount,
                        'totalFare' => $legPrices['totalFare'] * $typeCount,
                        'commission' => 0,
                        'payable' => $tpb = $legPrices['totalFare'] * $typeCount,
                        'passengersCount' => $typeCount
                    ]
                ];
            }


            ///> double checking balanceDue
            $toCompare = ($toCompare ?? 0) + (($bf + $tx) * $typeCount);
            // $a = $bf + $tx;
            // $av= ($bf + $tx) * $typeCount;
            // lugWarning("$bf + $tx  = $a  * $typeCount = $av =>> $toCompare",[]);

            //> calculating totl price
            $totalBaseFare = ($totalBaseFare ?? 0) + $tbf;
            $totalPayable = ($totalPayable ?? 0) + $tpb;
        }

        if (round($toCompare, 5) != round($source['priceQuote']['balanceDue'], 5)) {
            lugWarning('error in calculations', [$this->modifiedPassengersTypeCount, $toCompare, $source['priceQuote']['balanceDue'], $legPrices, $newPriceBreakDowns, $source]);
            return false;
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $totalBaseFare;
        $newPrice->payable = $totalPayable;
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function legMapper($leg)
    {
        $newLeg = new stdClass();
        $newLeg->segments = $newLeg->airlines = $legFlightNumbers = $resBookDesigCodes = $capacities = $bundles = [];
        $segments = $leg['segments'];
        $newLeg->stops = $leg['stops'];
        foreach ($segments as $key => $segment) {
            $marketingAirline = $operatingAirline = $this->airlines[$segment['identifier']['carrierCode']] ?? Null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['identifier']['carrierCode'],
                    'data_reference' => types('supplierDataReference', 'FlyAdeal')
                ];
                return false;
            }
            $cabin = ['YA' => 'Economy', 'JA' => 'Economy Premium'][$segment['details']['extraInfo']['productClass']];
            if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) { ///> this way we'll include VALUE/LIGHT/EXTRA options in the Economy results
                lugWarning(
                    'cabin mismatch',
                    // ['request' => $this->queryParams['cabin'], 'result' => $cabin, 'segment' => $segment]
                );
                return false;
            }

            if (count($segment['legs']) > 1) {
                lugWarning('unexpected data structure', [$segment, $this->raeResult['decoded']]);
                return false;
            }
            $extraInfo = $segment['legs'][0]['legInfo'];

            $departureAirport = $this->airports[$segment['designator']['origin']] ?? '';
            if (empty($departureAirport)) { ///> means the Airport is not in database
                lugWarning('DepartureAirport not found ' . ($segment), []);
                return false;
            }
            $arrivalAirport = $this->airports[$segment['designator']['destination']] ?? '';
            if (empty($arrivalAirport)) { ///> means the Airport is not in database
                lugWarning('ArrivalAirport not found ' . ($segment), []);
                return false;
            }
            $departureCity = $this->airports[$segment['designator']['origin']]['city']['title'];
            $arrivalCity = $this->airports[$segment['designator']['destination']]['city']['title'];
            $departureDateTime = $this->humanDateTime($segment['designator']['departure']);
            $arrivalDateTime = $this->humanDateTime($segment['designator']['arrival']);
            $departureTerminal = $extraInfo['departureTerminal'] ?? '-';
            $arivalTerminal = $extraInfo['arrivalTerminal'] ?? '-';

            $baggage = $this->getBaggage($segment['bundleInfo']['bundleCode']);
            $airplane = $this->airplanes[$extraInfo['equipmentType']]['title'] ?? $extraInfo['equipmentType'];
            $duration = $this->between2Time($extraInfo['departureTimeUtc'], $extraInfo['arrivalTimeUtc']);
            $afterFlight = $this->between2Time(
                $extraInfo['arrivalTimeUtc'],
                $segments[$key + 1]['legs'][0]['legInfo']['departureTimeUtc'] ?? $extraInfo['arrivalTimeUtc']
            );
            $legFlightNumbers[] = $flightNumber = $segment['identifier']['identifier'];
            $resBookDesigCodes[] = $resBookDesigCode = "{$segment['details']['extraInfo']['classOfService']}-{$segment['bundleInfo']['bundleCode']}"; ///> attaching them so it will show on search results
            $capacities[] = $capacity = (int) $segment['details']['availableCount'] ?? null;

            $newLeg->airlines[] = $marketingAirline['id'];
            $newLeg->segments[$key] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' => $segment['designator']['departure']
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' => $segment['designator']['arrival']
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
                'bundle' => $segment['bundleInfo']['bundleCode'] ///> all segments have to have the same bundle. we have made sure of it before
            ];
        }
        $firstSegmentDeparture = $leg['segments'][0]['legs'][0]['legInfo']['departureTimeUtc'];
        $lastSegmentArrival = $extraInfo['arrivalTimeUtc'];
        $legDuration =  $this->between2Time($firstSegmentDeparture, $lastSegmentArrival);
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

    protected function getFields($depends)
    {
        foreach ($this->translatedQueryParams['legs'] as $legIndex => $leg) {
            $OriginDestinationInformation[] = [
                "stations" => [
                    "originStationCodes" => [$leg['origin']['abb']],
                    "destinationStationCodes" => [$leg['destination']['abb']],
                    // "destinationStationCodes" => $leg['destination']['isCity'] ? $leg['destination']['airports']->pluck('supplier_key')->toArray() : [$leg['destination']['abb']],
                    "searchDestinationMacs" => false,
                    "searchOriginMacs" => false
                ],
                "dates" => ["beginDate" => $leg['departure']],
                "filters" => ["maxConnections" => 5, "compressionType" => 1, "BundleControlFilter" => 2, "productClasses" => ["YA", "JA"]],
                "ssrCollectionsMode" => 'NONE'
            ];
        }
        $fields = [
            "passengers" => [
                "types" => [
                    [
                        "type" => "ADT",
                        "count" => $this->translatedQueryParams['adults'],
                    ]
                ],
            ],

            "criteria" => $OriginDestinationInformation,
            "codes" => ["currencyCode" => $this->seller->currency->abb],
            "taxesAndFees" => 2,
            "numberOfFaresPerJourney" => 5
        ];
        if (!empty($this->translatedQueryParams['children'])) {
            $fields['passengers']['types'][] = [
                "type" => "CHD",
                "count" => (int) $this->translatedQueryParams['children']
            ];
        }
        if (!empty($this->translatedQueryParams['infants'])) {
            $fields['ssrs'] = ["INF"];
        }
        return $fields;
    }

    protected function getResultCurrency()
    {
        return $this->rawResult['decoded']['data']['currencyCode'];
    }

    protected function getExchangeUrl()
    {
        return '/api/nsk/v1/currency/converter';
    }

    protected function getExchangeResults()
    {
        return json_decode($this->rawResult['body'], true)['data'] ?? [];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return 'F3';
    }

    protected function getBCFields($depends)
    {
        foreach ($depends['bookingData'] as $data) {
            $keys[] = [
                "journeyKey" => $data['journeyKey'],
                "fareAvailabilityKey" => $data['fareAvailabilityKey'],
                "standbyPriorityCode" => '',
                "inventoryControl" => ''
            ];
        }
        $fields = [
            "keys" => $keys,
            "passengers" => ["types" => [["type" => "ADT", "count" => $this->modifiedPassengersTypeCount['adults']]]],
            "currencyCode" => $this->resultCurrency,
            "infantCount" => $this->modifiedPassengersTypeCount['infants'],
            "promotionCode" => '',
            "sourceOrganization" => '',
        ];
        if (!empty($this->modifiedPassengersTypeCount['children'])) {
            $fields['passengers']['types'][] = ["type" => "CHD", "count" => $this->modifiedPassengersTypeCount['children']];
        }
        return $fields;
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'city', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'passport_expiry', 'passport_number', 'passport_country', 'nationality', 'nationalId']
        ];
        $data = $this->addPassengerFormItem($items);
        $data['all'] = $this->addPassengerFormGenericItem('date', 'NationalId Expiry Date', $data['all']);
        if ($this->isDomesticFlight) {
            $data['all'] = $this->addPassengerFormGenericItem('text', 'Iqama', $data['all']);
            $data['all'] = $this->addPassengerFormGenericItem('date', 'Iqama Expiry Date', $data['all']);
        }
        return $data;
    }

    protected function getBaggage($bundleCode)
    {
        return [
            'FCAI' => 'No bags', ///> FLY, CAI Routes
            'PCAI' => '20kg', ///> FLY+, CAI Routes
            'MCAI' => '32kg', ///> FLYMAX, CAI Routes
            'FINT' => 'No bags', ///> FLY, Non- CAI Routes
            'PINT' => '20kg', ///> FLY+, Non- CAI Routes
            'MINT' => '32kg', ///> FLYMAX, Non- CAI Routes
            'FLY' => 'No bags', ///> FLY, Domestic Routes
            'FLYP' => '20kg', ///> FLY+, Domestic Routes
            'FLYM' => '32kg', ///> FLYMAX, Domestic Routes
        ][$bundleCode] ?? '-';
    }
}

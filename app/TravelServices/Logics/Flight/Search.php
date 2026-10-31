<?php

namespace App\TravelServices\Logics\Flight;

use App\Jobs\AirlinesFrequency;
use App\Jobs\InsertNewAirlines;
use App\TravelServices\Logics\Search as LogicsSearch;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\Country;
use App\Models\FlightBuffer;
use MatinUtils\NodeTracker\Models\ServiceInfo;
use App\TravelServices\Helpers\QueryParams;
use MatinUtils\NodeTracker\PriceInfo;

trait Search
{
    use LogicsSearch, QueryParams;
    protected $exceptionApplied = false, $passengersTypeCount, $passengersCount, $isOneWaySearch;
    protected $airlineSuppliers = [], $airlineFrequency = [];

    public function canBeDone()
    {
        ///> the suppliers who have stuff like session handling or preSearch APIs will re=write this
    }

    protected function getContent() ///> not used in accelaero Avtra Faranegar ITours Local Sepehr
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/$this->supplierName/FlightSearch.json"));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        $this->decodeRawResult();

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    protected function prepareTravelServiceData()
    {
        $this->setPassengersTypeCount();
        $this->setModifiedPassengersTypeCount();
        $this->passengersCount = $this->queryParams['adults'] + ($this->queryParams['children'] ?? 0); ///> not reading from passengerTypeCount bc that might have been modified
        $this->isOneWaySearch = empty($this->queryParams['legs'][1]);
    }

    protected function defaultSearchValidity()
    {
        $passengersCount = $this->queryParams['adults'] + ($this->queryParams['children'] ?? 0) + ($this->queryParams['infants'] ?? 0);
        $maxTime = $passengersCount * 2; ///> 2 min for each passenger
        return $maxTime < $this->minimumDefaultSearchValidity ? $this->minimumDefaultSearchValidity : $maxTime;
    }

    protected function itinerary($rawResults) ///> quantum flights don't use this. FlyErbil ITours don't have this. will update when we start using them
    {
        $skippedBySystem = $skippedByexception = $skippedForServiceInfo = $skippedForSellerInfo = $skippedForPriceInfo = 0;
        foreach ($rawResults as $itinerary) {
            $item = [];
            $this->prepareInfo($itinerary);
            if (!$this->checkValidatingAirlineCode($validdatingAirlineAbb = $this->validatingAirlineCode($itinerary))) {
                $skippedBySystem++;
                continue;
            }
            $this->airlineFrequency($validdatingAirlineAbb);
            if (!($item['service'] = $this->legModifier($this->originDestinationOption($itinerary), $validdatingAirlineAbb))) {
                $skippedForServiceInfo++;
                continue;
            }
            if (!$this->checkAccessExceptions($pricingConditions = $this->getPricingConditions($item['service']), $this->getAccessExceptions())) {
                $skippedByexception++;
                continue;
            }
            if (!$this->checkSystemExceptions($pricingConditions)) {
                $skippedBySystem++;
                continue;
            }
            if (!($item['price'] = $this->priceModifier($this->supplierRawPricing($itinerary), $pricingConditions))) {
                $skippedForPriceInfo++;
                continue;
            }
            if (!($item['sellerInfo'] = $this->sellerInfoModifier($itinerary))) {
                $skippedForSellerInfo++;
                continue;
            }
            $items[] = $item;
        }
        lugInfo("S>>>> $this->supplierName search Extractor items - " . $this->seller->alias, ['skippedBySystem' => $skippedBySystem, 'skippedByexception' => $skippedByexception, 'skippedForServiceInfo' => $skippedForServiceInfo, 'skippedForPriceInfo' => $skippedForPriceInfo, 'skippedForSellerInfo' => $skippedForSellerInfo, 'supplier raw response items count' => count($rawResults), 'Inserted items' => count($items ?? []), 'new airline Suppliers' => $this->airlineSuppliers]);
        return $items ?? [];
    }

    protected function legModifier($rawLegs, $validatingAirline) ///> all the flight suppliers use this except for Quantum supplier
    {
        $legs = $airlines = $stops = $checksums = [];
        $this->repelSingleMode($rawLegs);
        foreach ($rawLegs as $key => $rawLeg) {
            $mapped = $this->legMapper($rawLeg, $key);
            if (empty($mapped)) return false; ///> if one of the segments has a problem we skip the whole flight
            $legs[$key] = ['info' => $mapped->info, 'segments' => $mapped->segments];
            $stops[] = $mapped->stops ?? 0;
            $airlines[] = implode(',', $mapped->airlines);
            $checksums[] = $mapped->departureTime . '*' . $mapped->stops . '*' . implode('_', $mapped->airlines) . '*' . $mapped->info['flight_number'] . '*' . $mapped->resBookDesigCodes;
        }

        if (!$this->checkServiceInfoValidity($legs)) {
            // lugWarning(__METHOD__, ['functio error']);
            return false;
        }

        return [
            'supplier' => '',
            'searchValidity' => $this->searchValidityDeadline(),
            'legs' => $legs,
            'query_params' => $this->queryParams,
            'validatingAirline' => $this->airlines[$validatingAirline] ?? [$validatingAirline],
            'passengersCount' => $this->passengersTypeCount,
            'luxotaCode' => $this->seller->luxotaCode->code,
            'support' => ['pid' => ['search' => app('log-system')->getPid()]],
            'attachs' => [
                'fareRules' => $this->getFareRules($rawLegs),
                'stops' => implode(',', $stops),
                'airlines' => implode(';', $airlines),
                'checksum' => implode(';', $checksums),
                'times' => [
                    'startTime' => $legs[0]['info']['departure']['time'],
                    'returnTime' => $legs[1]['info']['departure']['time'] ?? null
                ],
            ],
        ];
    }

    protected function checkServiceInfoValidity($legs)
    {
        if (!$this->checkLegsDistance($legs)) {
            lugError('The time gap between flight legs is less than 1 hour', ['legs', $legs]);
            return false;
        }
        return true;
    }

    protected function priceModifier($rawPrices, $conditions)
    {
        if (!$mapped = $this->priceMapper($rawPrices)) return false; ///> Means there is a problem with the price
        $price = [
            'breakDowns' => $mapped->breakDowns ?? [],
            'payable' => $mapped->payable ?? 0,
            'baseFare' => $mapped->baseFare ?? 0,
            'diff' => $mapped->diff ?? 0
        ];
        return [
            'priceInfo' => $price,
            'guaranteed' => $mapped->guaranteed ?? false,
            'conditions' => $conditions,
            'rawPrices' => $mapped->raw ?? $rawPrices, ///> if mapper has not updated it (like in navitaire) we're using use the original that we got
        ];
    }

    protected function sellerInfoModifier($itinerary)
    {
        if (!$mapped = $this->sellerInfoMapper($itinerary)) return false; ///> Means there is a problem with the flight
        return [
            'onholdable' => $mapped->onholdable ?? false,
            'sellerCode' => $this->seller->abb, ///> for Quantum sellers will be overwritten by the one in the mapped->extra
            'passengersForm' => $this->getPassengersForm($itinerary),
            'beforeBooks' => $mapped->beforeBook,
            'afters' => $mapped->afterSearch
        ] + $mapped->extra ?? [];
    }

    protected function mapper()
    {
        if (!empty($this->items)) {
            $this->saveItemsToFile();
            $onholdable = $this->isOnholdable();
            $flights = $serviceInfo = [];
            foreach ($this->items as $key => $item) {
                if (!$this->passengerCountIsInvalid($item)) continue;
                extract($item['service']['attachs']);
                unset($item['service']['attachs']);
                $serviceInfoReferenceId = createReferenceId();
                if (!empty($fareRules)) {
                    $serviceInfo[] = [
                        'reference_id' => $serviceInfoReferenceId,
                        'type' => 'fareRules',
                        'data' => json_encode($fareRules),
                        'created_at' => Carbon::now()
                    ];
                }

                $serviceInfo[] = [
                    'reference_id' => $serviceInfoReferenceId,
                    'type' => 'serviceInfo',
                    'data' => json_encode($this->modifyServiceInfo($item['service'])), ///> can't do modifyServiceInfo in the leg mapper bc when we are reading from cache "leg mapper" won't happen
                    'created_at' => Carbon::now()
                ];

                $flights[] = [
                    'reference_id' => createReferenceId(),
                    'search_query_id' => $this->object->id,
                    'seller_id' => $this->seller->id,
                    'seller_info' => json_encode($item['sellerInfo']),
                    'service_info' => $serviceInfoReferenceId,
                    'price_info' => json_encode($this->modifySupplierPrice($item['price'])),
                    'currency_id' => getCurrency($this->queryParams['marketplace_currency']['id'])->id,
                    'total_b2b' => 0, ///> plugin will fill this
                    'total_b2c' => 0, ///> plugin will fill this
                    'is_charter' => false,
                    'airlines' => $airlines,
                    'cabin_type' => types('flightCabin', lcfirst($this->queryParams['cabin'])) ?? null,
                    'departure_period' => types('time', $this->timesPeriodNaming($times['startTime'] ?? null)),
                    'return_period' => $times['returnTime'] ? types('time', $this->timesPeriodNaming($times['returnTime'])) : null,
                    'stops' => $stops,
                    'is_onholdable' => ($item['sellerInfo']['onholdable'] ?? true) && $onholdable,
                    'checksum' => $checksum,
                    'created_at' => Carbon::now(),
                ];
            }

            $flights = $this->calculatePriceforEachItem($flights);
            $flights = $this->addTags($flights);
            $startserviceInfo = microtime(true);

            foreach (array_chunk($serviceInfo ?? [], 200) as $insertables) {
                ServiceInfo::insert($insertables);
            }
            $endServiceInfo = microtime(true) - $startserviceInfo;
            $start = microtime(true);
            foreach (array_chunk($flights ?? [], 100) as $flightBuffers) {
                FlightBuffer::insert($flightBuffers);
            }
            lugInfo('S>>>> ' . count($flights) . ' items inserted for ' . $this->seller->alias . '. Time: ' . (microtime(true) - $start), ['serviceInfo insert time' => $endServiceInfo]);
        }
        dispatch(new InsertNewAirlines(app('log-system')->getPid(), $this->airlineSuppliers))->onQueue('supplierData');
        dispatch(new AirlinesFrequency(app('log-system')->getPid(), $this->airlineFrequency, $this->seller->supplierService->data_reference))->onQueue('supplierData');
        return;
        // *api-out items get handled in their own class
    }

    protected function passengerCountIsInvalid($flight)
    {
        ///> since with modified passenger count we search for diff psngrfrm than the requested, we need to check if the flight has capacity. some suppliers don't gice us the capacity
        foreach ($flight['service']['legs'] as  $leg) {
            if ($leg['info']['capacity'] !== null && $leg['info']['capacity'] !== '-' &&  $leg['info']['capacity'] < $this->passengersCount) {
                lugWarning(__METHOD__, [$leg, $this->passengersCount]);
                return false;
            }
        }
        return true;
    }

    protected function humanDateTime($amdTime)
    {
        $carbon = Carbon::parse($amdTime);
        return [
            'dateTime' => $carbon->format('D, M d, Y H:i'),
            'time' => $carbon->format('H:i'),
        ];
    }

    protected function timesPeriodNaming($amdTime)
    {
        if (empty($amdTime)) {
            return null;
        }
        $hour = (int) Carbon::parse($amdTime)->format('H');
        if ($hour < 4) {
            return 'Mid-Night';
        }
        if ($hour < 8) {
            return 'Early-Morning';
        }
        if ($hour < 12) {
            return 'Morning';
        }
        if ($hour < 16) {
            return 'Mid-Day';
        }
        if ($hour < 20) {
            return 'Evening';
        }
        return 'Night';
    }

    protected function sumDurations(array $durations)
    {
        $time = strtotime('00:00');
        $total_time = 0;
        foreach ($durations as $ele) {
            $sec_time = strtotime($ele) - $time;
            $total_time = $total_time + $sec_time;
        }
        $hours = intval($total_time / 3600);
        $total_time = $total_time - ($hours * 3600);
        $min = intval($total_time / 60);
        return "$hours:$min";
    }

    protected function between2Time($start, $end, $legDistance = false)
    {
        if (empty($end)) {
            $end = $start;
        }
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);
        $diff = $end->diffInMinutes($start);
        if ($legDistance) {
            $startIsAfterEnd = $start->isAfter($end);
            if ($startIsAfterEnd || $diff < 60) {
                return false;
            }
            return true;
        }
        return intval($diff / 60) . ":" . ($diff % 60);
    }

    protected function priceDetailsMixer($list1, $list2)
    {
        foreach ($list1 as $key => $value) {
            if ($key == 'count') {
                continue;
            }
            $list1[$key] = $value + ($list2[$key] ?? 0);
        }
        return $list1;
    }

    public function loadAirLines($airlinecodes)
    {
        $supplierAirlines = $this->seller->getSupplierAirlines()->whereIn('supplier_key', $airlinecodes)->with('airline', function ($airlines) {
            $airlines->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb', 'logo');
        })->select('airline_id', 'supplier_key')->get();
        $airlines = [];
        foreach ($supplierAirlines as $row) {
            if (!empty($row->airline)) {
                $airlines[$row->supplier_key] = $row->airline->toArray() + ['supplier_key' => $row->supplier_key];
            }
        }
        return $airlines;
    }

    public function loadAirPorts($airportcodes)
    {
        $preferedDataReference = 'Amadeus';
        $supplierairports = $this->seller->getSupplierAirports($preferedDataReference)->whereIn('supplier_key', $airportcodes)
            ->with(['airport' => function ($airports) {
                $airports->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb', 'city_id');
            }, 'airport.city' => function ($cities) {
                $cities->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb', 'country_id');
            }, 'airport.city.country' => function ($airports) {
                $airports->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb');
            }])->select('airport_id')->get();
        $airports = [];
        foreach ($supplierairports as $row) {
            $airports[$row->airport->abb] = $row->airport->toArray();
        }
        return $airports;
    }

    public function loadAirPlanes($airplanecodes)
    {
        ///> Rewritten for CityNet Search 
        $preferedDataReference = 'Amadeus';
        $supplierairplanes = $this->seller->getSupplierAirplanes($preferedDataReference)->whereIn('supplier_key', $airplanecodes)->with('airplane')->get();
        $airplanes = [];
        foreach ($supplierairplanes as $row) {
            $airplanes[$row->airplane->abb] = $row->airplane->toArray();
        }
        return $airplanes;
    }

    public function setPassengersTypeCount()
    {
        $this->passengersTypeCount = [
            'adults' => $this->queryParams['adults'],
            'children' => $this->queryParams['children'] ?? 0,
            'infants' => $this->queryParams['infants'] ?? 0
        ];
    }

    public function setModifiedPassengersTypeCount()
    {
        $modifiedPassengerCount = $this->passengerModificationsRule();
        return $this->modifiedPassengersTypeCount = [
            'adults' => $this->modifyPassengerCount() ?  $modifiedPassengerCount[0] : $this->queryParams['adults'],
            'children' => $this->modifyPassengerCount() ?  $modifiedPassengerCount[1] : $this->queryParams['children'] ?? 0,
            'infants' => $this->modifyPassengerCount() ?  $modifiedPassengerCount[2] : $this->queryParams['infants'] ?? 0
        ];
    }

    public function airlineFrequency($supplierKey)
    {
        $this->airlineFrequency[$supplierKey] = ($this->airlineFrequency[$supplierKey] ?? 0) + 1;
    }

    public function isDomestic($en = 'Iran')
    {

        static $country;
        if (empty($country[$en])) {
            $country[$en] = Country::Where('en', $en)->first();
        }
        //> really shouldn't be useing queryParams['legs'][0] but since for now this is only used by suppliers that don't have multiDestination so
        return ($this->queryParams['legs'][0]['origin']['city']['country_id'] ?? 0) == $country[$en]->id && ($this->queryParams['legs'][0]['destination']['city']['country_id'] ?? 0) == $country[$en]->id;
    }

    protected function flightPricingConditions($serviceInfo)
    {
        $leg1 = $serviceInfo['legs'][0]['info'];
        return [
            'airline_id' => $serviceInfo['validatingAirline']['id'] ?? 'nan',
            'origin_airport_id' => $leg1['departure']['airport']['id'] ?? 'nan',
            'origin_country' => $leg1['departure']['airport']['city']['country_id'],
            'origin_city' => $leg1['departure']['airport']['city_id'],
            'destination_airport_id' => $leg1['arrival']['airport']['id'] ?? 'nan',
            'destination_country' => $leg1['arrival']['airport']['city']['country_id'],
            'destination_city' => $leg1['arrival']['airport']['city_id'],
            'cabin' => $leg1['cabin'],
            'tripType' => ['oneWay' => 'One_way', 'roundTrip' => 'Round_trip', 'multiDestination' => 'multiDestination', 'openReturn' => 'openReturn'][$this->queryParams['tripType']],
            'departureDate' => $this->queryParams['legs'][0]['departure'],
            'returnDate' => $this->queryParams['legs'][1]['departure'] ?? null,
        ];
    }

    protected function modifyServiceInfo($item)
    {
        //> the data might be provided from cache and by different characteristics
        ///> so we'll update the required items
        ///> for all the searches the modified passenger count is sent to the supplier but the info saved in the object, like the queryparams, ned to have the correct values
        ///> when we are reading from the saved files, the saved '100' file might have had a different original passengerCount, so we'll update i here
        $item['passengersCount'] = $this->passengersTypeCount;
        $item['query_params'] = $this->queryParams;
        $item['support'] = ['pid' => ['search' => app('log-system')->getPid()]];
        return $item;
    }

    protected function modifySupplierPrice($priceInfo)
    {
        ///> changing the price according to the original number of the passengers
        if ($this->passengerCountIsModified()) {  ///> if the passsenger count is actually changed
            $pssngrs = ['adults' => 'adult', 'children' => 'child', 'infants' => 'infant'];
            $percents = ['adults' => 100, 'children' => 100, 'infants' => 30];
            $payable = $baseFare = 0;
            $breakDowns = [];
            foreach ($this->passengersTypeCount as $passengersType => $count) {
                if ($this->modifiedPassengersTypeCount[$passengersType] == 0) {
                    $base = $priceInfo['priceInfo']['breakDowns']['adult'];
                    $percent = $percents[$passengersType];
                } else {
                    $base = $priceInfo['priceInfo']['breakDowns'][$pssngrs[$passengersType]];
                    $percent = 100;
                }
                if (!empty($count)) {
                    $breakDowns[$pssngrs[$passengersType]] = $breakDown = $this->updateBreakdowns($base, $percent, $this->queryParams[$passengersType]);
                    $payable += $breakDown['passengerTypeTotal']['payable'];
                    $baseFare += $breakDown['passengerTypeTotal']['baseFare'];
                }
            }
            $priceInfo['priceInfo'] = [
                'breakDowns' => $breakDowns ?? [],
                'payable' => round($payable ?? 0, 2),
                'baseFare' => round($baseFare ?? 0, 2),
                'diff' => round($payable - $baseFare, 2),
            ];
        }
        return $priceInfo;
    }

    protected function updateBreakdowns(array $priceList, $percent, $passengerTypeCount)
    {
        $newPriceList = [
            'baseFare' => $baseFare = round($priceList['baseFare'] / 100 * $percent, 2),
            'totalFare' => $totalFare = round($priceList['totalFare'] / 100 * $percent, 2),
            'tax' => $tax = round($priceList['tax'] / 100 * $percent, 2),
            'commission' => $commission = round($priceList['commission'] / 100 * $percent, 2),
            'payable' => $payable = round($priceList['payable'] / 100 * $percent, 2),
            'passengerTypeTotal' => [
                'baseFare' => $baseFare * $passengerTypeCount,
                'totalFare' => $totalFare * $passengerTypeCount,
                'tax' => $tax * $passengerTypeCount,
                'commission' => $commission * $passengerTypeCount,
                'payable' => $payable * $passengerTypeCount,
                'passengersCount' => $passengerTypeCount
            ]
        ];
        return $newPriceList;
    }

    protected function passengerCountIsModified()
    {
        return $this->modifiedPassengersTypeCount != $this->passengersTypeCount;
    }

    protected function prepareInfo($itinerary)
    {
        ///> some suppliers like avtra and sepehr need to prepare some info to use in service/ price mapper. they'll rewrite this
    }

    protected function getFareRules($flight)
    {
        ///> some suppliers like Local and Nira ahaveTheir fareRules available in search. they'll rewrite this
    }

    protected function decodeRawResult() ///> json suppliers use this, XML suppliers rewrite this
    {
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
    }

    protected function addTags($flights)
    {
        foreach ($flights as $key => $flight) {
            $priceInfo = json_decode($flight['price_info'], true);

            ///> pricing Rule sources
            $sellerHasMarginAmount = !empty(PriceInfo::getSellerMargin($priceInfo, 'search')['amount']);
            $marginException = PriceInfo::getRuleId($priceInfo, 'marginException');
            $basicRule = PriceInfo::getRuleId($priceInfo, 'basicRule');
            $basicRuleException = PriceInfo::getRuleId($priceInfo, 'basicRuleException');
            $ruleSource = !empty($basicRuleException) ? 'bre-' : 'br-';
            $ruleId = !empty($basicRuleException) ? "$basicRuleException," : (!empty($basicRule) ? "$basicRule," : '0,');
            $sellerMarginSource = !empty($marginException) ? 'sme-' : 'sm-';
            $marginId = !empty($marginException) ? "$marginException," : "{$this->seller->id},";

            ///> exchange sources
            $exchanges = '';
            foreach (PriceInfo::getSearchExchangeIds($priceInfo) as $exchangeId) {
                $exchanges .= "exch:$exchangeId,";
            }

            ///> final tag
            // exch:95,exch:94,
            // br-356,exch:89,exch:94,
            // br-297,sme-105,
            $flights[$key]['tags'] = "$ruleSource$ruleId$sellerMarginSource$marginId$exchanges";
        }
        return $flights;
    }

    protected function checkValidatingAirlineCode($validdatingAirlineAbb) ///> rewritten for QUANTUM
    {
        if (empty($this->airlines[$validdatingAirlineAbb])) {
            $this->airlineSuppliers[] = [
                'supplier_key' => $validdatingAirlineAbb,
                'data_reference' => $this->dataReference,
            ];
            return false;
        }
        return $this->filterZagrosAirline($this->airlines[$validdatingAirlineAbb]);
    }

    protected function removeDuplicates()
    {
        $searchQueryId = $this->object->id;
        $table = 'flight_buffers';
        $total = $this->object->flights->count();
        ///> Commenting this line to use it later for testing
        // $groupBy = app('db')->table($table)->select(app('db')->raw("count(*), `checksum`"))->where('search_query_id', $searchQueryId)->groupBy('checksum')->get();
        // $duplicated = app('db')->select(app('db')->raw("SELECT * FROM `$table` f1 INNER JOIN $table f2 where f1.search_query_id = $searchQueryId and f2.search_query_id = $searchQueryId and f1.checksum = f2.checksum and f1.total_b2b > f2.total_b2b"));
        app('db')->statement("DELETE f1 FROM `$table` f1 INNER JOIN $table f2 where f1.search_query_id = $searchQueryId and f2.search_query_id = $searchQueryId and f1.checksum = f2.checksum and f1.total_b2b > f2.total_b2b");

        lugInfo('Removed Duplicates', [
            'initial count' => $total,
            // 'count of groupBy checksum' => count($groupBy),
            // 'count of duplicated records' => count($duplicated),
            'final result count' => $this->object->flights->count(),
            // 'groupBy checksum (unique checksums)' => $groupBy,
            // 'duplicated records' => $duplicated
        ]);
    }

    protected function checkLegsDistance($legs)
    {
        $legCount = count($legs);
        for ($i = 0; $i < $legCount - 1; $i++) {
            $currentLeg = $legs[$i];
            $nextLeg = $legs[$i + 1];

            $lastSegmentCurrentLeg = end($currentLeg['segments']);
            $firstSegmentNextLeg = reset($nextLeg['segments']);

            $arrivalDateTime = Carbon::parse($lastSegmentCurrentLeg['arrival']['raw_time'] ?? $lastSegmentCurrentLeg['arrival']['rawTime']);
            $departureDateTime = Carbon::parse($firstSegmentNextLeg['departure']['raw_time'] ??  $firstSegmentNextLeg['departure']['rawTime']);
            // Extract dates
            $arrivalDate =   $arrivalDateTime->toDateString();
            $departureDate = $departureDateTime->toDateString();
            if ($arrivalDate !== $departureDate) {
                continue;
            } else {
                $timeCheck = $this->between2Time($arrivalDateTime, $departureDateTime, true);
                if (!$timeCheck) {
                    return false;
                }
            }
        }
        return true;
    }

    protected function filterZagrosAirline($validatingAirline)
    {
        ///> TS2390 some airlines won't accept non-iranian passengers, if the nodes wants it we'll remove it from the search result
        return !(strtolower($validatingAirline['abb'] ?? '') == 'zv' && $this->isDomestic() && hasPluginAccess("airlinePassengerLimits"));
    }
}

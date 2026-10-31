<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use stdClass;

trait Search
{
    use FlightSearch;

    protected $latestSession, $exchangeData;
    public function canBedone()
    {
        if ($this->queryParams['cabin'] == 'first') {
            return $this->addError('first class flights are unavailable');
        }
        $this->authorizeRequest();
        if (!$this->translatedQueryParams = $this->translateQueryParams(true)) {
            return $this->addError('Query params translation failed');
        }

        //checking is seleted route in Navitaire-routes or not 
        $filePath = storage_path('supplierRoutes/Navitaire.json');
        $fileContent = File::get($filePath);
        if (File::exists($filePath) && !empty(File::get($filePath))) {
            $flightRoutes = json_decode($fileContent, true);
            if (!empty($flightRoutes)) {
                if (!$this->checkFlightRoutes($flightRoutes)) {
                    $this->addError("flight is not in Navitaire routes");
                    return false;
                }
            }
        }
    }

    public function requestGenerator()
    {
        // $this->LugTheResponse = 1;
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->translatedQueryParams;
        lugInfo("Navitaire Search Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' =>  $this->cleanUrlSlash($auth['url']) . $this->getSearchUrl(),
            'timeout' => 20,
            'apiMethod' => 'Search'
        ];
        return $this->action();
    }

    protected function getSearchUrl()
    {
        return '/api/jz/v1/Availability';
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded']['data']['availabilityv4']['results'] ?? false;
        if (empty($rawResults[0]['trips']) || count($rawResults) != count($this->queryParams['legs'])) {
            $this->addError('Empty results');
            return;
        }
        $this->extractSupplierCodes();
        $this->saveRawResponse();
        $this->findExchangeRate();
        if (empty($this->exchangeData)) {
            ///> means the api failed. we'll fail the process bc we can't calculate amount
            return $this->addError('cound not get exchange rate');
        }
        $this->resultCurrency = $this->rawResult['decoded']['data']['availabilityv4']['currencyCode'];
        $this->items = $this->itinerary($this->recoomendations($rawResults));
    }

    protected function recoomendations($rawResults)
    {
        $faresAvailabile = collect($this->rawResult['decoded']['data']['availabilityv4']['faresAvailable']);

        ///> the legs come seperately, each leg has multiple items that we want to combine
        foreach ($rawResults as $legIndex => $legResults) {
            if (count($legResults['trips']) > 1) {
                lugWarning("unexpected. probably bc of multiAirportCity", [$rawResults]);
                return [];
            }
            $journeysAvailableByMarkets = $legResults['trips'][0]['journeysAvailableByMarket'];
            foreach ($journeysAvailableByMarkets as $journeyIndex => $journeysAvailableByMarket) {
                foreach ($journeysAvailableByMarket['value'] as $itemKey => $item) {
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
                $finalCombo[$legIndex] = $rawResults[$legIndex]['trips'][0]['journeysAvailableByMarket'][$journeyIndex]['value'][$itemIndex];
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
        foreach ($firstCombinations as $combination) {
            $combinationTemp = [];
            foreach ($combination as $legIndex => $leg) {
                foreach ($leg['fares'] as $fareIndex => $fare) {
                    if (count($fare['details']) > 1) {
                        lugWarning("unexpected fare details count", [$fare, $rawResults]);
                        return [];
                    }
                    if ($fare['details'][0]['status'] == 'Active') {
                        $combinationTemp[$legIndex][] = [$fareIndex];
                    }
                }
            }
            $FareCombinations = array_shift($combinationTemp);
            foreach ($combinationTemp as $comb) {
                $temp = [];
                foreach ($comb as $fareKey) {
                    foreach ($FareCombinations as $firstLegFareKey) {
                        $temp[] = array_merge($firstLegFareKey, $fareKey);
                    }
                }
                $FareCombinations = $temp;
            }
            foreach ($FareCombinations as $FareCombination) {
                $finalCombination = [];
                foreach ($FareCombination as $legKey => $fareKey) {
                    $fare = $combination[$legKey]['fares'][$fareKey] + ['extraInfo' =>  $faresAvailabile->where('key', $combination[$legKey]['fares'][$fareKey]['fareAvailabilityKey'])->first()];
                    if (count($fare['extraInfo']['value']['fares']) > 1) {
                        lugWarning("unexpected final fare count", [$rawResults, $finalCombination]);
                        return [];
                    }
                    $leg = $combination[$legKey] + ['productClass' => $fare['extraInfo']['value']['fares'][0]['productClass'], 'availableSeats' => $fare['details'][0]['availableCount']];
                    unset($leg['fares']);
                    $finalCombination['legs'][$legKey] = $leg;
                    $finalCombination['fare'][$legKey] = $fare;
                    $finalCombination['bookingData'][$legKey] = [
                        'origin' => $combination[$legKey]['designator']['origin'],
                        'destination' => $combination[$legKey]['designator']['destination'],
                        'journeyKey' => $combination[$legKey]['journeyKey'],
                        'fareAvailabilityKey' => $fare['fareAvailabilityKey']
                    ];
                }
                $finalCombinations[] = $finalCombination;
            }
        }
        return $finalCombinations;
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['legs'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return array_keys($this->airlines)[0] ?? '';
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['fare'];
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = false; //> can't onHold bc navitaire doesn't have tickets and once we get the pnr the book is finished
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = ['tripSell' => false, 'addPassengers' => false];
        $info->extra = [
            'resultCurrency' => $this->resultCurrency,
            'bookingData' => $itinerary['bookingData']
        ];
        return $info;
    }

    protected function priceMapper($legsPrice) ///> rewriten in custom search
    {
        ///> the prices for each leg comes seperately. infants' price don't come in search result at all
        ///> combining total and breakDowns of all the legs
        foreach ($legsPrice as $legIndex => $price) {
            $passengerFares = $price['extraInfo']['value']['fares'][0]['passengerFares'];
            foreach ($passengerFares as $fareInfo) {
                $breakDowns[$fareInfo['passengerType']] = [
                    "baseFare" => ($breakDowns[$fareInfo['passengerType']]['baseFare'] ?? 0) + ($addToRawPrice[$legIndex]['breakdowns'][$fareInfo['passengerType']]['baseFare'] = $this->exchangeSellerPrices($fareInfo['revenueFare'])),
                    "totalFare" => ($breakDowns[$fareInfo['passengerType']]['totalFare'] ?? 0) + ($addToRawPrice[$legIndex]['breakdowns'][$fareInfo['passengerType']]['totalFare'] = $this->exchangeSellerPrices($fareInfo['fareAmount']))
                ];
            }
            ///> the infant price details are not available so by PO's order we'll consider it 12 percent of the adult price
            if (!empty($this->translatedQueryParams['infants'])) {
                $breakDowns['INF'] = [
                    "baseFare" => ($breakDowns['INF']['baseFare'] ?? 0) + ($addToRawPrice[$legIndex]['breakdowns']['INF']['baseFare'] = ($addToRawPrice[$legIndex]['breakdowns']['ADT']['baseFare'] * 12 / 100)),
                    "totalFare" => ($breakDowns['INF']['totalFare'] ?? 0) + ($addToRawPrice[$legIndex]['breakdowns']['INF']['totalFare'] = ($addToRawPrice[$legIndex]['breakdowns']['ADT']['totalFare'] * 12 / 100))
                ];
            }
            $total = [
                "baseFare" => ($total['baseFare'] ?? 0) + ($addToRawPrice[$legIndex]['baseFare'] = $this->exchangeSellerPrices($price['extraInfo']['value']['totals']['revenueTotal'] + $addToRawPrice[$legIndex]['breakdowns']['INF']['baseFare'])),
                "totalFare" => ($total['totalFare'] ?? 0) +  ($addToRawPrice[$legIndex]['totalFare'] = $this->exchangeSellerPrices($price['extraInfo']['value']['totals']['fareTotal'] + $addToRawPrice[$legIndex]['breakdowns']['INF']['totalFare']))
            ];
        }
        $newPrice = new stdClass();
        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;
        foreach ($breakDowns as $type => $priceDetails) {
            $passengerType = ['ADT' => 'adult', 'CHD' => 'child', 'INF' => 'infant'][$type];
            $typeCount = ${$passengerType . 'Count'};
            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['baseFare'],
                'tax' => $tx = $priceDetails['totalFare'] - $priceDetails['baseFare'],
                'totalFare' => $priceDetails['totalFare'],
                'commission' => 0,
                'payable' => $priceDetails['totalFare'],
                'passengerTypeTotal' => [
                    'baseFare' => $priceDetails['baseFare'] * $typeCount,
                    'tax' => $tx * $typeCount,
                    'totalFare' => $priceDetails['totalFare'] * $typeCount,
                    'commission' => 0,
                    'payable' => $priceDetails['totalFare'] * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];
            $totalToCheck = ($totalToCheck ?? 0) + ($priceDetails['totalFare'] * $typeCount);
        }

        $newPrice->raw = ['exchangeData' => $this->exchangeData, 'exchanged' => $addToRawPrice, 'legs' => $legsPrice];
        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $total['baseFare'];
        $newPrice->payable = $total['totalFare'];
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function legMapper($leg)
    {
        $newLeg = new stdClass();
        $newLeg->segments = $newLeg->airlines = $legFlightNumbers = $resBookDesigCodes = $capacities = [];
        $segments = $leg['segments'];
        $newLeg->stops = $leg['stops'];
        foreach ($segments as $key => $segment) {
            $marketingAirline = $operatingAirline = $this->airlines[$segment['identifier']['carrierCode']] ?? Null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['identifier']['carrierCode'],
                    'data_reference' => types('supplierDataReference', 'Navitaire')
                ];
                return false;
            }
            $cabin = ['EL' => 'Economy LIGHT', 'EV' => 'Economy VALUE', 'EE' => 'Economy EXTRA', 'BU' => 'Business'][$leg['productClass']];
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

            $baggage = ['EL' => 'NO BAGGAGE', 'EV' => '20KG/1 PIECE', 'EE' => '30KG/2 PIECES', 'BU' => '60 KG/3 PIECES'][$leg['productClass']];
            $airplane = $this->airplanes[$extraInfo['equipmentType']]['title'] ?? $extraInfo['equipmentType'];
            $duration = $this->between2Time($extraInfo['departureTimeUtc'], $extraInfo['arrivalTimeUtc']);
            $afterFlight = $this->between2Time(
                $extraInfo['arrivalTimeUtc'],
                $segments[$key + 1]['legs'][0]['legInfo']['departureTimeUtc'] ?? $extraInfo['arrivalTimeUtc']
            );
            $legFlightNumbers[] = $flightNumber = $segment['identifier']['identifier'];
            $resBookDesigCodes[] = $resBookDesigCode = '-'; ///> this is only available in booking quote api, so until it's not important to have it in search we'll avoid calling the booking quote here. we'll update it later in beforeBook when we called the api
            $capacities[] = $capacity = (int) $leg['availableSeats'] ?? null;

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
                'capacity' => $capacity
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

    protected function extractSupplierCodes()
    {
        ///> find all the airports airlines and airplanes from the response in our database
        preg_match_all('/"origin":"(\w+)"/', $this->rawResult['body'], $origin);
        preg_match_all('/"destination":"(\w+)"/', $this->rawResult['body'], $destination);
        preg_match_all('/"carrierCode":"(\w+)"/', $this->rawResult['body'], $airlineCodes);
        preg_match_all('/equipmentType":"(\w+)"/', $this->rawResult['body'], $equipments);
        $this->airlines = $this->loadAirLines(array_unique($airlineCodes[1]));
        $this->airports = $this->loadAirports(array_unique(array_merge($origin[1], $destination[1])));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments[1]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['airlines' => $airlineCodes[1], 'airports' => array_merge($origin[1], $destination[1]), 'airplanes' => $equipments[1], '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'city', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate']
        ];
        return $this->addPassengerFormItem($items);
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
                "filters" => ["fareInclusionType" => "Default", "compressionType" => "CompressByProductClass"]
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
            "taxesAndFees" => "TaxesAndFees",
        ];
        if (!empty($this->translatedQueryParams['children'])) {
            $fields['passengers']['types'][] = [
                "type" => "CHD",
                "count" => (int) $this->translatedQueryParams['children']
            ];
        }
        if (!empty($this->translatedQueryParams['infants'])) {
            $fields['passengers']['types'][] = [
                "type" => "INF",
                "count" => (int) $this->translatedQueryParams['infants']
            ];
        }
        return $fields;
    }

    protected function findExchangeRate()
    {
        $responseCurrency = $this->getResultCurrency();
        if (strtoupper($responseCurrency) == strtoupper($this->seller->currency->abb)) {
            return $this->exchangeData = ['text' => 'no exchange needed', 'exchangeRate' => 1, 'roundingFactor' => 0.100];
        }
        $searchRawResult = $this->rawResult; ///> so we can put it back after the exchange api
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/Navitaire/CurrencyConverter.json"));
        } else {
            $this->call = [
                'fields' => '',
                'method' => 'GET',
                'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . $this->getExchangeUrl() . "?FromCurrencyCode=$responseCurrency&ToCurrencyCode={$this->seller->currency->abb}&Amount=1",
                'timeout' => 20,
                'apiMethod' => 'CurrencyConverter'
            ];
            $this->action();
        }
        $this->exchangeData = $this->getExchangeResults();
        lugInfo('ExchangeData', [$this->exchangeData]);
        $this->rawResult = $searchRawResult;
        return $this->exchangeData;
    }

    protected function getResultCurrency()
    {
        return $this->rawResult['decoded']['data']['availabilityv4']['currencyCode'];
    }

    protected function getExchangeUrl()
    {
        return '/api/jz/v1/CurrencyConverter';
    }

    protected function getExchangeResults()
    {
        return json_decode($this->rawResult['body'], true)['data']['currencyCalculation'] ?? [];
    }

    protected function checkFlightRoutes($flightRoutes)
    {
        $this->translatequeryParams = $this->translateQueryParams();
        foreach ($this->translatequeryParams['legs'] as $leg) {
            $route = $leg['origin']['abb'] . '-' . $leg['destination']['abb'];
            return array_search($route, $flightRoutes) !== false;
        }
        return true;
    }
}

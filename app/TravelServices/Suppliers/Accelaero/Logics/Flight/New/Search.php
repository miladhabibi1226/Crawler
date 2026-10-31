<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight\New;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use App\TravelServices\Suppliers\Accelaero\Logics\Flight\Search as CommonAccelaeroSearch;
use stdClass;
use DateTime;
use App\Models\SupplierSession;
use Carbon\Carbon;
use App\TravelServices\Handler;

trait Search
{
    use FlightSearch, CommonAccelaeroSearch, Recomendations {
        CommonAccelaeroSearch::initializeDefaultCity insteadof FlightSearch;
        CommonAccelaeroSearch::priceModifier insteadof FlightSearch;
    }

    protected  $finalRecomendations, $finalRecomendations2, $latestSession, $priceRawResult, $translatedQueryParams, $JourneyDuration, $OriginDestinationInformations = [], $supplierRawResultItems = [], $originAirports, $destinationAirports, $baggageInfo = [];

    protected function canBeDone()
    {
        $this->findOrCreateSupplierSession();
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

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $this->translatedQueryParams = $this->translateQueryParams();
        $headerTripType = match ($this->translatedQueryParams['tripType'] ?? '') {
            'oneWay' => 'ONEWAY',
            'roundTrip' => 'RETURN',
            'multiDestination' => 'MULTICITY',
        };
        $this->call = [
            'fields' => json_encode($this->getFields()),
            'url' => $this->cleanUrlSlash($auth['searchUrl']),
            'headers' => array_merge([
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->latestSession,
            ], $this->getExtraHeaders($auth)),
            'timeout' => 10,
            'apiMethod' => 'Search'
        ];
        return $this->action();
    }

    protected function getFields()
    {
        foreach ($this->translatedQueryParams['legs'] as $legIndex => $leg) {
            $searchOnds[] = [
                "origin" => [
                    "code" => $leg['origin']['abb'],
                    "locationType" => $leg['origin']['isCity'] == true ?  "CITY" : "AIRPORT"
                ],
                "destination" => [
                    "code" => $leg['destination']['abb'],
                    "locationType" => $leg['destination']['isCity'] == true  ? "CITY" : "AIRPORT"
                ],
                "searchStartDate" => $leg['departure'],
                "searchEndDate" =>  $leg['departure'],
                "preferredDate" => null,
                "cabinClass" => ['ECONOMY' => 'Y', 'BUSINESS' => 'C', 'FIRST' => 'F', 'PREMIUMECONOMY' => 'S'][strtoupper($this->translatedQueryParams['cabin'])],
                "interlineQuoteDetails" => null
            ];
        }
        $paxCounts = [
            [
                "count" => $this->translatedQueryParams['adults'],
                "paxType" => "ADT"
            ],
            [
                "count" => $this->translatedQueryParams['children'],
                "paxType" => "CHD"
            ],
            [
                "count" => $this->translatedQueryParams['infants'],
                "paxType" => "INF"
            ]
        ];

        $data = [
            "searchOnds" =>  $searchOnds,
            "paxCounts" => $paxCounts,
            "currencyCode" => $this->seller->currency->abb,
            "cabinClass" => ['ECONOMY' => 'Y', 'BUSINESS' => 'C', 'FIRST' => 'F', 'PREMIUMECONOMY' => 'S'][strtoupper($this->translatedQueryParams['cabin'])]
        ];

        return $data;
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded']['ondWiseFlightCombinations'] ?? '';
        if (empty($rawResults)) {
            $this->addError('Empty Items');
            return false;
        }
        $this->saveRawResponse();
        $this->extractSupplierCodes($rawResults);
        $this->isDomesticFlight = $this->isDomestic();
        $this->finalRecomendations = $this->getRecommendations($rawResults);
        if (empty($this->finalRecomendations)) {
            $this->addError('Empty Items - some routs have empty flights in supplier response');
            return false;
        };
        $this->getPriceForAllResults();
        $this->getBaggageForAllResults();
        foreach ($this->OriginDestinationInformations as $flightNumbers => $OriginDestinationInformation) {
            $baggageResult = empty($OriginDestinationInformation['baggageResult']) ? [[]] : $OriginDestinationInformation['baggageResult'];
            $this->repelSingleMode($baggageResult);
            foreach ($baggageResult as $baggageOption) {
                $itineraries[] = [
                    'flightNumbers' => $flightNumbers,
                    'JSESSIONID' => $this->priceRawResult[$flightNumbers]['cookie'][0],
                    'session' => $OriginDestinationInformation['priceQuoteResult']['TransactionIdentifier'],
                    'rawResult' => $this->priceRawResult[$flightNumbers]['body'],
                    'priceQuoteResult' => $OriginDestinationInformation['priceQuoteResult'],
                    'legs' => $OriginDestinationInformation['legs'],
                    'baggageOption' => $baggageOption,
                ];
            }
        }
        $this->items = $this->itinerary($itineraries);
    }

    protected function extractSupplierCodes($searchRawResult)
    {
        preg_match_all('/"airportCode":"(\w+)"/', $this->rawResult['body'], $airportCodes);
        preg_match_all('/"aircraftModel":"([^"]*)"/', $this->rawResult['body'], $airplanes);
        preg_match_all('/"flightNumber":"(\w{2})\w*"/', $this->rawResult['body'], $airlineCodes);
        $this->airlines = $this->loadAirLines(array_unique($airlineCodes[1]));
        $this->airplanes = $this->loadAirplanes(array_unique($airplanes[1]));
        $this->airports = $this->loadAirports(array_unique($airportCodes[1]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$searchRawResult[body]' => $searchRawResult['body'], 'crrencies' =>  $crrencies, 'airlines' =>  $airlineCodes[1], 'airports' =>  $airportCodes[1], '$this->airports' => $this->airports, '$this->airlines' => $this->airlines]);
    }

    protected function getPriceForAllResults($JSESSIONID = null)
    {
        $this->sendMultiCurlForPrice($JSESSIONID);
        $this->removePriceResultsWithError();
        foreach ($this->OriginDestinationInformations as $flightNumbers => $OriginDestinationInformation) {
            ///> $this->rawResult is now the result of PriceQuotes multi curl
            $priceQuoteResult = $this->rawResult[$flightNumbers]['decoded'] ?? null;
            if (empty($priceQuoteResult)) {
                unset($this->OriginDestinationInformations[$flightNumbers]);
            } elseif (!$this->compareSegments($priceQuoteResult, $OriginDestinationInformation)) {
                unset($this->OriginDestinationInformations[$flightNumbers]);
                lugWarning('The number of segments in the price result is not equal to the number of segments in the search result.', ['search-segments ' => $OriginDestinationInformation, 'price-segments' => $priceQuoteResult[0]['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary']['AirItinerary']]);
            } else {
                $this->OriginDestinationInformations[$flightNumbers]['priceQuoteResult'] = $priceQuoteResult[0]['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary'];
                $this->OriginDestinationInformations[$flightNumbers]['priceQuoteResult']['TransactionIdentifier'] = $priceQuoteResult[0]['OTA_AirPriceRS']['@attributes']['TransactionIdentifier'];
                SupplierSession::create([
                    'session' =>  $priceQuoteResult[0]['OTA_AirPriceRS']['@attributes']['TransactionIdentifier'],
                    'expire_on' => Carbon::now()->addMinutes(10),
                    'seller_id' => $this->seller->id,
                    'searcher_identity' => $this->object->searcher_identity ?? 'Fake_Searcher_Identity',
                ]);
            }
        }
        $this->call = $this->rawResult = [];
    }

    protected function collectOriginDestinationInformations()
    {
        foreach ($this->finalRecomendations as $finalRecomendation) {
            $flightNumbers = [];
            foreach ($finalRecomendation['legs'] as $segments) {
                foreach ($segments as $segment) {
                    $flightNumbers[] = $segment['flightNumber'];
                }
            }
            $key = implode(' / ', $flightNumbers);
            $this->OriginDestinationInformations[$key] = $finalRecomendation;
        }
    }

    protected function legMapper($leg)
    {
        $legFlightNumbers = $resBookDesigCodes = [];
        $cabin = $this->queryParams['cabin'];
        $newLeg = new stdClass();
        $newLeg->airlines = [];
        $capacities = [];
        $newLeg->stops = count($leg) - 1;
        $newLeg->segments = [];
        foreach ($leg as $key => $segment) {
            $capacity = null;
            $flightNumber = $segment['flightNumber'];
            $marketingAirline = $operatingAirline = $this->airlines[substr($flightNumber, 0, 2)] ?? null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => substr($flightNumber, 0, 2),
                    'data_reference' => types('supplierDataReference', 'Accelaero'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }
            //duration
            $segDepartureDateTime = new DateTime($segment['departureDateTimeZulu']);
            $segArrivalDateTime = new DateTime($segment['arrivalDateTimeZulu']);
            $interval = $segDepartureDateTime->diff($segArrivalDateTime);
            $duration = $interval->format('%H:%I');
            $durations[] = $duration;
            $durations[] = $afterFlight = $this->between2Time(
                $segment['arrivalDateTimeLocal'],
                $segments[$key + 1]['departureDateTimeLocal'] ?? $segment['arrivalDateTimeLocal']
            );

            $cabin = ['Y' => 'Economy', 'C' => 'Business', 'F' => 'First'][$segment['cabinPrices']['cabinClass']] ?? null;
            if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) {
                // lugWarning(
                //     'Cabin Mismatch',
                //     [$segment, ['Y' => 'Economy', 'C' => 'Business', 'F' => 'First']]
                // );
                return false;
            }

            $departureCity = $this->airports[$segment['origin']['airportCode']]['city']['title'] ?? $segment['origin']['airportCode'];
            $departureDateTime = $this->humanDateTime($segment['departureDateTimeLocal']);
            $departureAirport = $this->airports[$segment['origin']['airportCode']] ?? $segment['origin']['airportCode'];
            $departureTerminal = $segment['origin']['terminal'] ?? '-';

            $arrivalCity = $this->airports[$segment['destination']['airportCode']]['city']['title'] ?? $segment['destination']['airportCode'];
            $arrivalDateTime = $this->humanDateTime($segment['arrivalDateTimeLocal']);
            $arrivalAirport = $this->airports[$segment['destination']['airportCode']] ?? $segment['destination']['airportCode'];
            $arivalTerminal = $segment['destination']['terminal'] ?? '-';

            $baggage = $this->baggageInfo['baggageCode'] ?? 'No Bag';
            $airplane = []; ///> not in the result
            $legFlightNumbers[] = $flightNumber;
            $resBookDesigCodes[] = $resBookDesigCode = ''; ///> you can send it in the request but it's not in the result
            if (!empty($segment['availablePaxCounts'])) {
                foreach ($segment['availablePaxCounts'] as $availablePaxCount) {
                    if ($availablePaxCount['paxType'] === 'ADT') {
                        $capacities[] =  $capacity = $availablePaxCount['count'] ?? null;
                        break;
                    }
                }
            }

            $newLeg->airlines[] = $marketingAirline['id'];
            $newLeg->segments[] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' => $segment['departureDateTimeLocal']
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' => $segment['arrivalDateTimeLocal']
                ],
                'airline' => $marketingAirline,
                'operatingAirline' => $operatingAirline,
                'cabin' => $cabin,
                'luggage' => $baggage,
                'hand_baggage' => '',
                'airplane' => $airplane,
                'duration' => $duration['time'] ?? '0:0',
                'ticket_type' => 'system',
                'flight_number' => $flightNumber,
                'after_flight_stop' => $afterFlight,
                'resBookDesigCode' => $resBookDesigCode,
                'RPH' => '',
                'capacity' => $capacity
            ];
        }

        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[count($newLeg->segments) - 1]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $cabin,
            'luggage' => '',
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' => $this->sumDurations($durations),
            'connections' => count($leg) - 1,
            'ticket_type' => 'system',
            'flight_number' => implode('/', $legFlightNumbers),
            'after_flight_stop' => '-',
            'resBookDesigCode' => implode('/', $resBookDesigCodes),
            'RPH' => $newLeg->segments[0]['RPH'],
            'capacity' => empty($capacities) ? '-' : min($capacities),
            'refunable' => ''
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = implode('/', $resBookDesigCodes);
        return $newLeg;
    }

    protected function originDestinationOption($itinerary)
    {
        $this->baggageInfo = $itinerary['baggageOption'];
        return $itinerary['legs'];
    }

    protected function getBaggageOrgDesInfoAndTransactionId($OriginDestinationInformation)
    {
        return [
            'TransactionIdentifier' => $OriginDestinationInformation['priceQuoteResult']['TransactionIdentifier'],
            'OriginDestinationInformation' => $OriginDestinationInformation['priceQuoteResult']['AirItinerary']
        ];
    }

    protected function getJsessionid($JSESSIONID, $flightNumber)
    {
        $cookie = $this->priceRawResult[$flightNumber]['cookie'][0] ?? '';
        preg_match_all('/(JSESSIONID=(.*?));/', $cookie, $output_array);
        return $output_array[1][0] ?? '';
    }

    protected function haveSupplierResponseError() //> re-written for search
    {
        $rawErrors = [];
        if (!empty($this->rawResult['decoded']['serviceErrors'])) {
            $rawErrors = $this->rawResult['decoded']['serviceErrors'];
        }
        if (!empty($this->rawResult['decoded']['responseSupplementaryInfo'])) {
            $rawErrors[] = $this->rawResult['decoded']['responseSupplementaryInfo'];
        }
        if (!empty($rawErrors)) {
            lugWSResError("Accelaero response contain error ({$this->seller->alias})", ['supplier-error: ' => $rawErrors, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawErrors);
            return $rawErrors;
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("Accelaero response is invalid ({$this->seller->alias})", ['supplier-response: ' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'Invalid Response';
        }
        $this->supplierConversaion();
        return false;
    }

    protected function prepareRawODO($itinerary)
    {
        preg_match('/<ns1:OriginDestinationOptions>(.*?)<\/ns1:OriginDestinationOptions>/ims', $itinerary['rawResult'], $rawODO);
        $rawOdo = preg_replace('/<ns1:AABundledServiceExt\b[^>]*>.*?<\/ns1:AABundledServiceExt>/s','', $rawODO[0]);
        return  $rawOdo;
    }

    public function findOrCreateSupplierSession()
    {
        $activeSession = SupplierSession::where('seller_id', $this->seller->id)
            ->where('session', 'AirArabiaSignIn')
            ->first();
        if (!empty($activeSession)) {
            $this->supplierSession = $activeSession;
            $this->latestSession = $this->supplierSession->extras;

        } else {
            $signIn = Handler::signIn($this->seller);
            if (!$signIn['status'] ?? false) {
                return false;
            }
            $this->supplierSession = $signIn['supplierSession'];
            $this->latestSession = $this->supplierSession->extras;
        }
        return true;
    }
}

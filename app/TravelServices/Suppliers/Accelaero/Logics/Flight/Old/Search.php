<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight\Old;

use App\TravelServices\Suppliers\Accelaero\Logics\Flight\Search as CommonAccelaeroSearch;
use App\Models\SupplierSession;
use App\TravelServices\Logics\Flight\Search as FlightSearch;
use Carbon\Carbon;
use DateInterval;
use stdClass;

trait Search
{
    use FlightSearch, CommonAccelaeroSearch {
        CommonAccelaeroSearch::initializeDefaultCity insteadof FlightSearch;
        CommonAccelaeroSearch::priceModifier insteadof FlightSearch;
    }

    protected $latestSession, $priceRawResult, $translatedQueryParams, $JourneyDuration, $OriginDestinationInformations = [], $supplierRawResultItems = [], $originAirports, $destinationAirports, $baggageInfo = [];


    public function canBeDone()
    {
        if($this->queryParams['tripType'] === 'multiDestination'){
            $this->addError("there is no multiDestination search for old version of AirArabia ");
            return false;
        }
    }
    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $xml = file_get_contents(storage_path('Samples/Accelaero/FlightSearch.xml'));
            //> remove ns1: bc they caused error
            $xml = preg_replace('/ns1:/m', '', $xml);
            $this->rawResult['body'] = $xml;
            $xml = new \SimpleXMLElement($xml);
            $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//soap:Body')), true);
            $this->latestSession = 'fromFile';
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Request generator unsuccessful");
            return false;
        }

        ///> we used multiCurls so
        $errors = [];
        $curlCount = count($this->rawResult);
        foreach ($this->rawResult as $index => $rawResult) {
            $num = $index + 1;
            $error = $this->haveSupplierResponseErrorNew($rawResult, ".Search curl $num of $curlCount");
            if (!empty($error)) {
                $errors[] = $error;
                unset($this->rawResult[$index]);
                continue;
            }
            //> remove ns1: bc they caused error
            $xml = preg_replace('/ns1:/m', '', $rawResult['body']);
            $xml = new \SimpleXMLElement($xml);
            $this->rawResult[$index]['decoded'] = json_decode(json_encode($xml->xpath('//soap:Body')), true);
            $this->rawResult[$index]['session'] = $this->rawResult[$index]['decoded'][0]['OTA_AirAvailRS']['@attributes']['TransactionIdentifier'];
            SupplierSession::create([
                'session' =>  $this->rawResult[$index]['session'],
                'expire_on' => Carbon::now()->addMinutes(10),
                'seller_id' => $this->seller->id,
                'searcher_identity' => $this->object->searcher_identity ?? 'Fake_Searcher_Identity',
            ]);
        }
        ///> will addError Only if ALL the curls has error
        if (count($errors) >= $curlCount) {
            return $this->addError($errors);
        }
        $this->saveRawResponse('xml');
    }

    public function requestGenerator()
    {
        ///> Accelaero doesn't provide multiAirportCity search, so we use multi curl
        ///> even if both are airports we make only one call but via multiCurl

        // $this->LugTheRequest = false;
        // $this->LugTheResponse = true;
        $this->multi = true;
        $auth = $this->seller->api->configs;
        $this->translatedQueryParams = $this->translateQueryParams(true);

        ///> only when multiAirportCity is chosen leg['origin']['airports'] exists. if it's empty leg['origin']['abb'] is the airport code
        ///> we don't have multiDestination for now so maximum 2 legs. and we know that for roundtrip flights the 2nd leg is just the oposite of the first one
        $this->originAirports = $this->translatedQueryParams['legs'][0]['origin']['airports'] ?? [$this->translatedQueryParams['legs'][0]['origin']['abb']];
        $this->destinationAirports = $this->translatedQueryParams['legs'][0]['destination']['airports'] ?? [$this->translatedQueryParams['legs'][0]['destination']['abb']];
        // luginfo("Accelaero Search Data ({$this->seller->alias})", ['originAirports' => $this->originAirports, 'destinationAirports' => $this->destinationAirports, 'query_params' => $this->queryParams,  'translatedQueryParams' => $this->translatedQueryParams, 'auth' => $auth]);

        foreach ($this->originAirports as $originAirport) {
            foreach ($this->destinationAirports as $destinationAirport) {
                $this->translatedQueryParams['legs'][0]['origin']['abb'] = $originAirport;
                $this->translatedQueryParams['legs'][0]['destination']['abb'] = $destinationAirport;
                if (!empty($this->translatedQueryParams['legs'][1])) {
                    $this->translatedQueryParams['legs'][1]['destination']['abb'] = $originAirport;
                    $this->translatedQueryParams['legs'][1]['origin']['abb'] = $destinationAirport;
                }
                $this->prepareCalls($auth, $this->translatedQueryParams);
            }
        }
        return $this->action();
    }

    protected function prepareCalls($auth, $translatedQueryParams)
    {
        $depends = $translatedQueryParams + $auth;
        $this->call[] = [
            'fields' => $this->minifyXML(view('Accelaero.FlightSearch', $depends)->render()),
            'url' => $this->cleanUrlSlash($auth['url']),
            'headers' => [],
            'timeout' => 10,
            'apiMethod' => 'Search'
        ];
    }

    protected function extractor()
    {
        $searchRawResults = $this->rawResult; ///> since rawResult will be rewritten later for price quotes we keep a copy here
        $this->rawResult = [];
        foreach ($searchRawResults as $searchRawResult) {
            $this->extractSupplierCodes($searchRawResult);
            $this->searchRawResult = $searchRawResult;
            $rawResults =  $this->searchRawResult['decoded'][0]['OTA_AirAvailRS']['AAAirAvailRSExt']['PricedItineraries'];
            if (empty($rawResults)) {
                return $this->addError('Empty pricedItineraries');
            }

            if ($this->resultCurrency != $this->seller->currency->abb) {
                lugError('Currency mismatch', ['seller currency' => $this->seller->currency->abb, 'resultCurrency' => $this->resultCurrency ?? '']);
                return $this->addError('Currency mismatch');
            }

            $cookie = $this->searchRawResult['cookie'][0] ?? '';
            preg_match_all('/(JSESSIONID=(.*?));/', $cookie, $output_array);
            $JSESSIONID = $output_array[1][0] ?? '';
            if (empty($JSESSIONID)) {
                return $this->addError('Empty JSESSIONID');
            }

            $this->getPriceForAllResults($JSESSIONID);
            $this->getBaggageForAllResults($JSESSIONID);
            foreach ($this->OriginDestinationInformations as $flightNumbers => $OriginDestinationInformation) {
                $baggageResult = empty($OriginDestinationInformation['baggageResult']) ? [[]] : $OriginDestinationInformation['baggageResult'];
                $this->repelSingleMode($baggageResult);
                foreach ($baggageResult as $baggageOption) {
                    $itineraries[] = [
                        'flightNumbers' => $flightNumbers,
                        'JSESSIONID' => $JSESSIONID,
                        'session' => $searchRawResult['session'],
                        'rawResult' => $this->priceRawResult[$flightNumbers]['body'],
                        'priceQuoteResult' => $OriginDestinationInformation['priceQuoteResult'],
                        'baggageOption' => $baggageOption,
                    ];
                }
            }
        }
        $this->items = $this->itinerary($itineraries ?? []);
    }

    protected function getPriceForAllResults($JSESSIONID)
    {
        $this->sendMultiCurlForPrice($JSESSIONID);
        $this->removePriceResultsWithError();
        foreach ($this->OriginDestinationInformations as $flightNumbers => $OriginDestinationInformation) {
            ///> $this->rawResult is now the result of PriceQuotes multi curl
            $priceQuoteResult = $this->rawResult[$flightNumbers]['decoded'] ?? null;
            if (empty($priceQuoteResult)) {
                unset($this->OriginDestinationInformations[$flightNumbers]);
            } else {
                $this->OriginDestinationInformations[$flightNumbers]['priceQuoteResult'] = $priceQuoteResult[0]['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary'];
            }
        }
        $this->call = $this->rawResult = [];
    }

    protected function collectOriginDestinationInformations() ///> re-written in custom search
    {
        $OriginDestinationInformations = $this->searchRawResult['decoded'][0]['OTA_AirAvailRS']['OriginDestinationInformation'];
        $this->repelSingleMode($OriginDestinationInformations);
        foreach ($OriginDestinationInformations as $index => $OriginDestinationInformation) {
            $segments = $OriginDestinationInformation['OriginDestinationOptions']['OriginDestinationOption'];
            $this->repelSingleMode($segments);
            $flightNumbers = array_column(array_column(array_column($segments, 'FlightSegment'), '@attributes'), 'FlightNumber');
            $flightNumbers = implode('_', $flightNumbers);
            $this->OriginDestinationInformations[$flightNumbers]['OriginDestinationOptions']['OriginDestinationOption'] = $segments;
        }
    }

    protected function collectBaggageResult()
    {
        $curlCount = count($this->rawBaggageResult);
        $num = 0;
        foreach ($this->rawBaggageResult ?? [] as $key => $priceQuotesResult) {
            $num++;
            $error = $this->haveSupplierResponseErrorNew($priceQuotesResult, ". Baggage curl $num of $curlCount");
            if (!empty($error)) {
                unset($this->rawBaggageResult[$key]);
                continue;
            }
            //> remove ns1: bc they caused error
            $xml = preg_replace('/ns1:/m', '', $priceQuotesResult['body']);
            $xml = new \SimpleXMLElement($xml);
            $this->rawBaggageResult[$key]['decoded'] = json_decode(json_encode($xml->xpath('//soap:Body')), true);
        }
    }


    protected function legMapper($leg)
    {
        $segments = $leg['FlightSegments'];
        $legFlightNumbers = $resBookDesigCodes = [];
        $cabin = $this->queryParams['cabin'];
        $newLeg = new stdClass();
        $newLeg->airlines = [];
        $newLeg->stops = count($segments) - 1;
        $newLeg->segments = [];
        foreach ($segments as $key => $segment) {
            $segment = $segment['FlightSegment'];
            $flightNumber = $segment['@attributes']['FlightNumber'];
            preg_match_all('/FlightNumber="' . $flightNumber . '" JourneyDuration="(\w+)/', $this->searchRawResult['body'], $JourneyDuration); //> find a better way to get the JourneyDuration
            if (empty($JourneyDuration[1][0])) {
                lugWarning("Accelaero JourneyDuration", [$JourneyDuration, $this->searchRawResult['body']]);
                return false;
            }
            $segmentDuration =  new DateInterval($JourneyDuration[1][0] . 'S');
            $afterFlight = $this->between2Time($segment['@attributes']['ArrivalDateTime'], $segments[$key + 1]['FlightSegment']['@attributes']['DepartureDateTime'] ?? $segment['@attributes']['ArrivalDateTime']);
            list($h, $i) = explode(':', $afterFlight);
            $legDuration['h'] = $segmentDuration->h + $h + ($legDuration['h'] ?? 0);
            $legDuration['i'] = $segmentDuration->i + $i + ($legDuration['i'] ?? 0); ///> CLEAN THIS UP

            $marketingAirline = $operatingAirline = $this->airlines[substr($flightNumber, 0, 2)] ?? null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => substr($flightNumber, 0, 2),
                    'data_reference' => types('supplierDataReference', 'Accelaero'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }

            $departureCity = $this->airports[$segment['DepartureAirport']['@attributes']['LocationCode']]['city']['title'] ?? $segment['DepartureAirport']['@attributes']['LocationCode'];
            $departureDateTime = $this->humanDateTime($segment['@attributes']['DepartureDateTime']);
            $departureAirport = $this->airports[$segment['DepartureAirport']['@attributes']['LocationCode']] ?? $segment['DepartureAirport']['@attributes']['LocationCode'];
            $departureTerminal = $segment['DepartureAirport']['@attributes']['Terminal'] ?? '-';

            $arrivalCity = $this->airports[$segment['ArrivalAirport']['@attributes']['LocationCode']]['city']['title'] ?? $segment['ArrivalAirport']['@attributes']['LocationCode'];
            $arrivalDateTime = $this->humanDateTime($segment['@attributes']['ArrivalDateTime']);
            $arrivalAirport = $this->airports[$segment['ArrivalAirport']['@attributes']['LocationCode']] ?? $segment['ArrivalAirport']['@attributes']['LocationCode'];
            $arivalTerminal = $segment['ArrivalAirport']['@attributes']['Terminal'] ?? '-';

            $baggage = $this->baggageInfo['baggageCode'] ?? 'No Bag';
            $airplane = []; ///> not in the result
            $duration = $segmentDuration->format('%H:%I');
            $legFlightNumbers[] = $flightNumber;
            $resBookDesigCodes[] = $resBookDesigCode = ''; ///> you can send it in the request but it's not in the result

            $newLeg->airlines[] = $marketingAirline['id'];
            $newLeg->segments[] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' => $segment['@attributes']['DepartureDateTime']
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' => $segment['@attributes']['ArrivalDateTime']
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
                'RPH' => $segment['@attributes']['RPH'],
                'capacity' => null
            ];
        }
        $legDuration['h'] =  $legDuration['h'] + intval($legDuration['i'] / 60); ///> very ugly but i didbn't have time
        $legDuration['i'] = ($legDuration['i'] % 60);
        $legDuration = new DateInterval('PT' . $legDuration['h'] . 'H' . $legDuration['i'] . 'M0S');
        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[count($newLeg->segments) - 1]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $cabin,
            'luggage' => '',
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' => $legDuration->format('%H:%I'),
            'connections' => count($segments) - 1,
            'ticket_type' => 'system',
            'flight_number' => implode('/', $legFlightNumbers),
            'after_flight_stop' => '-',
            'resBookDesigCode' => implode('/', $resBookDesigCodes),
            'RPH' => $newLeg->segments[0]['RPH'],
            'capacity' => null
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = implode('/', $resBookDesigCodes);
        return $newLeg;
    }

    protected function extractSupplierCodes($searchRawResult)
    {
        ///> find all the airports and airlines from the response in our database
        preg_match_all('/LocationCode="(\w+)"/', $searchRawResult['body'], $airportCodes);
        preg_match_all('/FlightNumber="(\w\w)\d+"/', $searchRawResult['body'], $airlineCodes);
        preg_match_all('/CurrencyCode="(\w+)"/', $searchRawResult['body'], $crrencies);
        $this->airlines = $this->loadAirLines(array_unique($airlineCodes[1]));
        $this->airports = $this->loadAirports(array_unique($airportCodes[1]));
        $this->airplanes = []; ///> not in the result
        $this->resultCurrency = $crrencies[1][0] ?? '';
        if (empty($this->resultCurrency)) {
            lugwarning('accelaero no result currency', [$searchRawResult['body'], $crrencies]);
        }
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$searchRawResult[body]' => $searchRawResult['body'], 'crrencies' =>  $crrencies, 'airlines' =>  $airlineCodes[1], 'airports' =>  $airportCodes[1], '$this->airports' => $this->airports, '$this->airlines' => $this->airlines]);
    }


    protected function legsCleaner($result)
    {
        ///> in the result there is nothing to show which segment belongs to which leg so we gotta find out ourselves
        $OriginDestinationOptions = $result['OriginDestinationOption'];
        $this->repelSingleMode($OriginDestinationOptions);
        foreach ($OriginDestinationOptions as $OriginDestinationOption) {
            $index = $OriginDestinationOption['FlightSegment']['DepartureAirport']['@attributes']['LocationCode'] . '/' . $OriginDestinationOption['FlightSegment']['ArrivalAirport']['@attributes']['LocationCode'];
            $segments[$index] = $OriginDestinationOption;
        }
        $org = $this->originAirports;
        $des = $this->destinationAirports;
        $legs[0] = $this->getLeg($segments, $org, $des);
        if (!$this->isOneWaySearch) {
            $legs[1] = $this->getLeg($segments, $des, $org);
        }
        return $legs;
    }

    protected function getLeg($segments, $legOrigins, $legDestinations)
    {
        list($firstSegment, $firstSegmentDestination) = $this->getSegment($segments, $legOrigins);
        $leg['FlightSegments'] = [$firstSegment];
        if (!in_array($firstSegmentDestination, $legDestinations)) { ///> it means the leg has more than one segment
            list($secondSegment, $secondSegmentDestination) = $this->getSegment($segments, [$firstSegmentDestination], $legDestinations);
            if (empty($secondSegmentDestination)) { ///> means there might be more than 2 segment for the leg
                $this->addError('MORE THAN 2 SEGMENTS are not handled');
                lugError('MORE THAN 2 SEGMENTS are not handled', ['firstSegmentDestination' => [$firstSegmentDestination], 'legDestinations' => $legDestinations, 'legOrigins' => $legOrigins]);
            } else {
                $leg['FlightSegments'][] = $secondSegment;
            }
        }
        return $leg;
    }

    protected function getSegment($segments, $segOrgs, $segDes = [])
    {
        foreach ($segments as $key => $segment) {
            list($og, $ds) = explode('/', $key);
            if (in_array($og, $segOrgs) && (empty($segDes) || in_array($ds, $segDes))) {
                return [$segment, $ds];
            }
        }
        return [null, null];
    }

    protected function originDestinationOption($itinerary)
    {
        $this->baggageInfo = $itinerary['baggageOption'];
        return $this->legsCleaner($itinerary['priceQuoteResult']['AirItinerary']['OriginDestinationOptions']);
    }

    protected function getBaggageOrgDesInfoAndTransactionId($OriginDestinationInformation)
    {
        return [
            'TransactionIdentifier' => $this->searchRawResult['session'],
            'OriginDestinationInformation' => $OriginDestinationInformation
        ];
    }

    protected function getJsessionid($JSESSIONID, $flightNumber)
    {
        return $JSESSIONID;
    }

    protected function prepareRawODO($itinerary)
    {
        preg_match('/<ns1:OriginDestinationOptions>(.*?)<\/ns1:OriginDestinationOptions>/ims', $itinerary['rawResult'], $rawODO);
        return  $rawODO[0];
    }
}

<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use stdClass;

trait Search
{
    use FlightSearch;
    protected $latestSession, $translatedQueryParams, $baggages;

    public function canBeDone()
    {
        $this->authorizeRequest();
    }

    protected function getContent() ///> not used in accelaero Avtra Faranegar ITours Local Sepehr
    {
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/$this->supplierName/FlightSearch.json"));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        $errors = [];
        $curlCount = count($this->rawResult);
        //we use multi-curl
        foreach ($this->rawResult as $index => $rawResult) {
            $num = $index + 1;
            $rawResult['decoded'] = json_decode($rawResult['body'], true);
            $error = $this->searchHaveSupplierResponseError($rawResult, ".Search curl $num of $curlCount");
            if (!empty($error)) {
                $errors[] = $error;
                unset($this->rawResult[$index]);
                continue;
            }
            $this->rawResult[$index]['decoded'] = $rawResult['decoded'];
        }
        ///> will addError Only if ALL the curls has error
        if (count($errors) >= $curlCount) {
            return $this->addError($errors);
        }
    }

    protected function requestGenerator()
    {
        $this->translatedQueryParams = $this->translateQueryParams(true);
        $this->multi = true;
        // $this->LugTheResponse = true;

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
                $this->prepareCalls();
            }
        }

        return $this->action();
    }

    protected function prepareCalls()
    {
        $this->call[] = [
            'fields' => json_encode($this->getFields()),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/v2.2/Search/Flight",
            'timeout' => 20,
            'apiMethod' => 'Search'
        ];
    }

    protected function getFields()
    {
        foreach ($this->translatedQueryParams['legs'] as $legIndex => $leg) {
            $originDestinationInformation[] = [
                "DepartureDateTime" => $leg['departure'],
                "OriginLocationCode" => $leg['origin']['abb'],
                "DestinationLocationCode" => $leg['destination']['abb'],
            ];
        }
        $passengers = [["Code" => "ADT", "Quantity" => $this->translatedQueryParams['adults']]];
        if ($this->translatedQueryParams['children'] > 0) {
            $passengers[] =  ["Code" => "CHD", "Quantity" => $this->translatedQueryParams['children']];
        }
        if (!empty($this->translatedQueryParams['infants'])) {
            $passengers[] =  ["Code" => "INF", "Quantity" => $this->translatedQueryParams['infants']];
        }
        $data = [
            "OriginDestinationInformations" => $originDestinationInformation,
            "TravelPreferences" => [
                "CabinPreference" => ['ECONOMY' => 'Y', 'BUSINESS' => 'C', 'FIRST' => 'F', 'PREMIUMECONOMY' => 'S'][strtoupper($this->translatedQueryParams['cabin'])],
                "Preferences" => [
                    "CabinClassPreference" => [
                        "CabinType" => ['ECONOMY' => 'Y', 'BUSINESS' => 'C', 'FIRST' => 'F', 'PREMIUMECONOMY' => 'S'][strtoupper($this->translatedQueryParams['cabin'])],
                        "PreferenceLevel" => "Restricted",
                    ],
                ],
                "AirTripType" => $this->translatedQueryParams['tripType'] == "oneWay" ? "OneWay" : "Return",
            ],
            // "PricingSourceType" => "Public",
            "PassengerTypeQuantities" => $passengers,
            "NearByAirports" => false,
            "Target" => "Test", ///////////////////>>>>>>>>>>>>>>>>>>>>>>>>>>
            "ConversationId" => "string",
        ];

        return $data;
    }

    protected function extractor()
    {

        foreach ($this->rawResult ?? [] as $thisRawResults) {
            if (!empty($thisRawResults['decoded']['Data'])) {
                $rawResults[] = $thisRawResults['decoded']['Data'];
            }
        }
        if (empty($rawResults)) {
            $this->addError('Empty Items');
            lugError('Empty Items', [$this->rawResult]);
            return;
        }


        if (!$this->checkCurrencyCode($rawResults)) {
            $this->addError('currency mismatch');
            return;
        }
        $this->saveRawResponse();
        $this->extractSupplierCodes();
        $this->items = $this->itinerary($this->recomendations($rawResults));
    }

    protected function extractSupplierCodes()
    {
        foreach ($this->rawResult as $rawResult) {
            preg_match_all('/"DepartureAirportLocationCode":"(\w+)"/', $rawResult['body'], $departureAirportCodes);
            preg_match_all('/"ArrivalAirportLocationCode":"(\w+)"/', $rawResult['body'], $arrivalAirportCodes);
            preg_match_all('/"MarketingCarriercode":"(\w+)"/', $rawResult['body'], $mAirlineCodes);
            preg_match_all('/"OperatingCarrierCode":"(\w+)"/', $rawResult['body'], $OAirlineCodes);
            preg_match_all('/"ValidatingCarrier":"(\w+)"/', $rawResult['body'], $vAirlineCodes);
            preg_match_all('/"Equipment":"(\w+)"/', $rawResult['body'], $equipments);
            $airportCodes = array_unique(array_merge($airportCodes ?? [], $departureAirportCodes[1], $arrivalAirportCodes[1]));
            $eqCodes = array_unique(array_merge($eqCodes ?? [], $equipments[1]));
            $airlineCodes = array_unique(array_merge($airlineCodes ?? [], $OAirlineCodes[1], $vAirlineCodes[1], $mAirlineCodes[1]));
        }
        $this->airlines = $this->loadAirLines(array_unique($airlineCodes));
        $this->airports = $this->loadAirports(array_unique($airportCodes));
        $this->airplanes = $this->loadAirplanes(array_unique($eqCodes));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['AirportCodes' => $airportCodes, 'AirlineCode' => $airlineCodes, '$equipments' => $eqCodes, '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function recomendations($rawResults)
    {
        $finalRawResult = [];
        foreach ($rawResults as $rawIndex => $rawResult) {
            $flightSegmentList = collect($rawResult['FlightSegmentList']);
            $itineraryReferenceList = collect($rawResult['ItineraryReferenceList']);
            $flightFaresList = collect($rawResult['FlightFaresList']);
            foreach ($rawResult['PricedItineraries'] as $pricedItineraries) {
                $pricedItineraries['PriceInfo'] =  $flightFaresList->where('FareRef', $pricedItineraries['FareRef'])->first();
                foreach ($pricedItineraries['OriginDestinations'] as $originDestinations) {
                    $pricedItineraries['OriginDestinations']['legs'][(int)$originDestinations['LegIndicator']]['FlightSegments'][] = array_merge($flightSegmentList->where('SegmentRef', $originDestinations['SegmentRef'])->first(), $itineraryReferenceList->where('ItineraryRef', $originDestinations['ItineraryRef'])->first());
                };
                $finalRawResult[] = $pricedItineraries;
            }
        }
        return $finalRawResult;
    }

    protected function legMapper($leg)
    {
        $segments = $leg['FlightSegments'];
        $cabin = $this->queryParams['cabin'];
        $newLeg = new stdClass();
        $newLeg->airlines = $newLeg->segments = $legFlightNumbers = $resBookDesigCodes = $capacities = [];
        $newLeg->stops = count($segments) - 1;

        foreach ($segments as $key => $segment) {
            $marketingAirline = $this->airlines[$segment['MarketingCarriercode']] ?? null;
            if (empty($marketingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['MarketingCarriercode'],
                    'data_reference' => types('supplierDataReference', 'MystiFly'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }
            $operatingAirline = $this->airlines[$segment['OperatingCarrierCode']] ?? null;
            if (empty($operatingAirline)) {
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['OperatingCarrierCode'],
                    'data_reference' => types('supplierDataReference', 'MystiFly'),
                ];
                return false;
            }

            $cabin = ["Y" => 'Economy', "C" => 'Business', "S" => 'Premium Economy', "F" => 'First',][$segment['CabinClassCode']] ?? null; ///> we're counting Premium Economy(2) as Economy
            if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) { ///> this way we'll include Premium/standard options in the results
                // lugWarning(
                //     'Cabin Mismatch',
                //     // [$segment, [1 => 'Economy', 2 => 'Economy', 3 => 'Business', 4 => 'Premium Business', 5 => 'First', 6 => 'Premium First', 100 => 'Default']]
                // );
                return false;
            }

            $departureCity = $this->airports[$segment['DepartureAirportLocationCode']]['city']['title'] ?? '';
            $departureDateTime = $this->humanDateTime($segment['DepartureDateTime']);
            $departureAirport = $this->airports[$segment['DepartureAirportLocationCode']] ?? '';
            if (empty($departureAirport)) {
                lugWarning('DepartureAirport not found ' . ($segment['DepartureAirportLocationCode'] ?? ''), [$segment]);
                return false;
            }
            $departureTerminal = '-';


            $arrivalCity = $this->airports[$segment['ArrivalAirportLocationCode']]['city']['title'] ?? '';
            $arrivalDateTime = $this->humanDateTime($segment['ArrivalDateTime']);
            $arrivalAirport = $this->airports[$segment['ArrivalAirportLocationCode']] ?? '';
            if (empty($arrivalAirport)) {
                lugWarning('ArrivalAirport not found ' . ($segment['ArrivalAirportLocationCode'] ?? ''), [$segment]);
                return false;
            }
            $arrivalTerminal = '-';

            $baggage = [];
            foreach ($segment['CheckinBaggage'] as $CheckinBaggage) {
                $baggage[] = $CheckinBaggage['Value'] . '/' . $CheckinBaggage['Type'];
            }

            $airplane = $this->airplanes[$segment['Equipment']]['title'] ?? $segment['Equipment'];

            $duration = $segment['JourneyDuration'];
            $hours = intdiv($duration, 60);
            $remainingMinutes = $duration % 60;
            // Format as HH:MM:SS
            $formattedDuration = sprintf('%02d:%02d:00', $hours, $remainingMinutes);
            $durations[] = $formattedDuration;
            $durations[] = $afterFlight = $this->between2Time(
                $segment['ArrivalDateTime'],
                $segments[$key + 1]['DepartureDateTime'] ?? $segment['ArrivalDateTime']
            );
            $legFlightNumbers[] = $flightNumber = $segment['MarketingFlightNumber'];
            $resBookDesigCodes[] = $resBookDesigCode = $segment['RBD'];
            $capacities[] = $capacity = (int) $segment['SeatsRemaining'] ?? null;
            $newLeg->airlines[] = $marketingAirline['id'];

            $newLeg->segments[$key] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' => $segment['DepartureDateTime'],
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arrivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' => $segment['ArrivalDateTime'],
                ],
                'airline' => $marketingAirline,
                'operatingAirline' => $operatingAirline,
                'cabin' => $this->queryParams['cabin'],
                'luggage' => implode(',', $baggage) ?? '--',
                'airplane' => $airplane,
                'duration' => $formattedDuration,
                'ticket_type' => 'system',
                'flight_number' => $flightNumber,
                'after_flight_stop' => $afterFlight,
                'resBookDesigCode' => $resBookDesigCode,
                'capacity' => $capacity
            ];
        }

        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[count($newLeg->segments) - 1]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $this->queryParams['cabin'],
            'luggage' => '',
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' =>  $this->sumDurations($durations),
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
        $priceBaseFare = 0;
        $priceTotalFare = 0;
        $newPrice = new stdClass();
        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;
        foreach ($price['PassengerFare'] as $priceDetails) {
            $passengerType = ['ADT' => 'adult', 'CHD' => 'child', 'INF' => 'infant'][$priceDetails['PaxType']];
            $typeCount = $priceDetails['Quantity'];
            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                // lugError($passengerType . ' count mismatch', ['passengersTypeCount' => $this->passengersTypeCount, 'result count' => $priceDetails['PassengerTypeQuantity']]);
            } else {
                ///> to check later that all the requested passengers types are available in the result
                ${$passengerType . 'Count'} = 'exists';
            }

            $tax = array_sum(array_column($priceDetails['TaxBreakUp'], 'Amount'));
            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['BaseFare'],
                'tax' => $tx = $tax,
                'totalFare' => $priceDetails['TotalFare'],
                'commission' => 0,
                'payable' => $priceDetails['TotalFare'],
                'passengerTypeTotal' => [
                    'baseFare' => $priceDetails['BaseFare'] * $typeCount,
                    'tax' => $tx * $typeCount,
                    'totalFare' => $priceDetails['TotalFare'] * $typeCount,
                    'commission' => 0 * $typeCount,
                    'payable' => $priceDetails['TotalFare'] * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];
            $priceBaseFare += $priceDetails['BaseFare'] * $typeCount;
            $priceTotalFare += $priceDetails['TotalFare'] * $typeCount;
        }

        ///> checking if all the passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            return false;
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $priceBaseFare;
        $newPrice->payable = $priceTotalFare;
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = $itinerary['PriceInfo']['FareType'] != 'Webfare';
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = ['revalidate' => false];
        $info->extra = [
            'FareSourceCode' => $itinerary['FareSourceCode'],
            'FareType' => $itinerary['PriceInfo']['FareType']
        ];
        return $info;
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['phone', 'text_area', 'email'],
            'all' => ['gender', 'first_name', 'last_name']
        ];
        $data = $this->addPassengerFormItem($items);
        return $data;
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['OriginDestinations']['legs'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['ValidatingCarrier'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['PriceInfo'];
    }

    protected function checkCurrencyCode($rawResults)
    {
        if (($rawResults[0]['FlightFaresList'][0]['Currency'] ?? $this->seller->currency->abb) != $this->seller->currency->abb) {
            lugError('currency mismatch', ['result' => $this->rawResult['decoded']['Data']['FlightFaresList'][0]['Currency'] ?? null, 'seller' => $this->seller->currency->abb]);
            return false;
        }
        return true;
    }

    protected function searchHaveSupplierResponseError($rawResult, $massage = '')
    {
        foreach ($rawResult as $key => $value) {
            $rawError = $rawResult['decoded']['Error'] ?? null;
            if (!empty($rawError)) {
                // lugWSResError("MystiFly response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $rawResult, 'supplier response: ' => $rawResult['body']]);
                $this->supplierConversaion($rawError["Message"] ?? $rawError);
                return $rawError["Message"] ?? $rawError;
            }
            if (($rawResult['decoded']['Success'] ?? null) != true) {
                $rawError = $rawResult['decoded']['Message'] ?? null;
                // lugWSResError("MystiFly response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError, 'supplier response: ' => $rawResult['body']]);
                $this->supplierConversaion($rawError["Message"] ?? $rawError);
                return $rawError["Message"] ?? $rawError;
            }
            if ($rawResult['http_code'] != 200) {
                // lugWSResError("MystiFly response is invalid ({$this->seller->alias})", ['rawResult' => $rawResult]);
                $this->supplierConversaion('invalid response status: ' . $rawResult['http_code']);
                return 'MystiFly response is invalid';
            }
            $this->supplierConversaion();
            return false;
        }
    }
}

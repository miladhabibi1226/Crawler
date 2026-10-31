<?php

namespace App\TravelServices\Suppliers\Faranegar\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use stdClass;

trait Search
{
    use FlightSearch;

    protected function canBeDone()
    {
        ///> Cabin Info: Economy => Y , Business => C , Empty => Both
        $Cabin = $this->object->query_params['cabin'];
        if ($Cabin == 'First') {
            lugWarning('IranAirTours only supports Economy and Business flights!');
            $this->addError('Invalid Cabin Code');
        }
        return true;
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            ///> Domestic Flight
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Faranegar/FlightSearch.json'));
            ///> International Flight
            // $this->rawResult['body'] = file_get_contents(storage_path('Samples/Faranegar/FlightSearchInternational.json'));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }

        ///> we used multiCurls so
        foreach ($this->rawResult as $index => $rawResult) {
            $this->rawResult[$index]['decoded'] = json_decode($rawResult['body'], true);
        }

        $resultErrors = $this->haveSupplierResponseError();
        foreach ($resultErrors ?? [] as $key => $error) {
            if (empty($error)) {
                return true;
            }
            $errors[] = $error;
        }
        if (!empty($errors)) {
            $this->addError($errors);
        }
    }

    public function requestGenerator()
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
            'url' => $this->cleanUrlSlash($auth['url']) . '/Air_Available',
            'apiMethod' => 'Search',
            'timeout' => 10
        ];
    }

    protected function extractor()
    {
        $this->saveRawResponse();
        foreach ($this->rawResult as $rawResult) {
            if (empty($rawResult['decoded']['PricedItineraries']['PricedItinerary']) || empty($rawResult['decoded']['PricedItineraries'])) {
                continue;
            }
            $rawResults = $rawResult['decoded']['PricedItineraries']['PricedItinerary'];
            if (!$this->checkCurrency($rawResult['decoded']['PricedItineraries']['PricedItinerary'][0]['Currency'])) {
                $this->addError('Currency Mismatch');
                lugError('Currency Mismatch', ['result currency' => ($rawResult['decoded']['PricedItineraries']['PricedItinerary'][0]['Currency'] ?? null), 'seller currency' => $this->seller->currency->abb]);
                $this->addError('Currency Mismatch');
                continue;
            }
            $flights[] = array_merge($rawResults);
        }
        if (empty($flights)) {
            $this->addError('No Result');
            return;
        }
        $combinedFlights = array_merge(...$flights);
        $this->extractSupplierCodes();
        $this->isDomesticFlight = $this->isDomestic();
        $this->items = $this->itinerary($combinedFlights);
    }

    protected function sellerInfoMapper($itinerary)
    {
        $bookData = [];
        foreach ($itinerary['AirItinerary']['OriginDestinationOptions'] as $flightSegment) {
            $bookData[] = $flightSegment['FlightSegment'][0]['BookingClassAvails'][0];
        }
        $info = new stdClass();
        $info->onholdable = true;
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = [];
        $info->extra = [
            'isIranianDomesticFlight' => $this->isDomesticFlight,
            'bookingData' => $bookData
        ];
        return $info;
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
                    'data_reference' => types('supplierDataReference', 'Faranegar')
                ];
                return false;
            }
            $marketingAirline = $this->airlines[$segment['MarketingAirline']['Code']] ?? Null;
            if (empty($marketingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['MarketingAirline']['Code'],
                    'data_reference' => types('supplierDataReference', 'Faranegar')
                ];
                return false;
            }
            $cabin = ($segment['BookingClassAvails'][0]['ResBookDesigCabinCode'] == "Y") ? "Economy" : "Business";
            if (strtoupper($cabin) != strtoupper($this->queryParams['cabin'])) {
                lugWarning(
                    'cabin mismatch',
                    // ['request' => $this->queryParams['cabin'], 'result' => $cabin, 'segment' => $segment]
                );
                return false;
            }
            $departureAirport = $this->airports[$segment['DepartureAirport']['LocationCode']] ?? '';
            if (empty($departureAirport)) { ///> means the Airport is not in database
                lugWarning('DepartureAirport not found ' . ($segment['DepartureAirportLocationCode']['LocationCode'] ?? ''), []);
                return false;
            }
            $arrivalAirport = $this->airports[$segment['ArrivalAirport']['LocationCode']] ?? '';
            if (empty($arrivalAirport)) { ///> means the Airport is not in database
                lugWarning('ArrivalAirport not found ' . ($segment['ArrivalAirportLocationCode']['LocationCode'] ?? ''), []);
                return false;
            }
            ///> check capacity
            if ($segment['BookingClassAvails'][0]['ResBookDesigQuantity'] < $this->passengersCount) {
                continue;
            }
            $capacities[] = $capacity = $segment['BookingClassAvails'][0]['ResBookDesigQuantity'] ?? null;
            $departureCity = $this->airports[$segment['DepartureAirport']['LocationCode']]['city']['title'] ?? $segment['DepartureAirport']['LocationCode'];
            $arrivalCity = $this->airports[$segment['ArrivalAirport']['LocationCode']]['city']['title'] ?? $segment['ArrivalAirport']['LocationCode'];
            $departureDateTime = $this->humanDateTime($segment['DepartureDateTime']);
            $arrivalDateTime = $this->humanDateTime($segment['ArrivalDateTime']);
            $departureTerminal = $segment['DepartureAirport']['Terminal'] ?? '-';
            $arivalTerminal = $segment['ArrivalAirport']['Terminal'] ?? '-';
            $allBaggeges = [];
            foreach ($segment['FreeBaggages'] as $bag) {
                $allBaggeges[] = $bag['Quantity'] . $bag['Unit'] . '/' . $bag['PassengerType'];
            }
            $baggage = !empty($allBaggeges) ? implode('-', $allBaggeges) : '';
            $airplane = $this->airplanes[$segment['Equipment']['AirEquipType']]['title'] ?? $segment['Equipment']['AirEquipType'];
            $afterFlight = $this->between2Time(
                $segment['ArrivalDateTime'],
                $segments[$key + 1]['DepartureDateTime'] ?? $segment['ArrivalDateTime']
            );
            $legFlightNumbers[] = $flightNumber = $segment['FlightNumber'];
            $legRPHNumbers[] = $RPH = $segment['RPH'];
            $newLeg->airlines[] = $marketingAirline['id'] ?? null;
            $resBookDesigCodes[] = $resBookDesigCode = $segment['BookingClassAvails'][0]['ResBookDesigCode'];
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
                'duration' => $segment['FlightDuration'],
                'ticket_type' => 'system',
                'flight_number' => $flightNumber,
                'after_flight_stop' => $afterFlight,
                'resBookDesigCode' => $resBookDesigCode,
                'capacity' => $capacity
            ];
        }
        ///> it's usually have a one segment
        $legDuration = $segments[0]['FlightDuration'];
        ///> if segment count is bigger than one
        if (count($segments) > 1) {
            lugWarning('Segment count is bigger than one!', ['Segments' => $segments]);
        }
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
            'RPH' => implode('/', $legRPHNumbers),
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
        ///> passengers type and count does't exist in supplier response , so we can't check it
        $newPrice = new stdClass();
        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;
        $breakDowns = $price['PTC_FareBreakdowns'];
        foreach ($breakDowns as $priceDetails) {
            $passengerType = ['ADL' => 'adult', 'CHD' => 'child', 'INF' => 'infant'][$priceDetails['PassengerTypeQuantity']['Code']];
            $typeCount = ${$passengerType . 'Count'};
            if (${$passengerType . 'Count'} == 0) {
                continue;
            }
            $sumOfTaxs = 0;
            foreach ($priceDetails['PassengerFare']['Taxes']['Tax'] as $tax) {
                $sumOfTaxs += $tax['Amount'];
            }
            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['PassengerFare']['BaseFare']['Amount'],
                'tax' => $tx = $sumOfTaxs,
                'totalFare' => $priceDetails['PassengerFare']['TotalFare']['Amount'],
                'commission' => $priceDetails['PassengerFare']['MarkupFare']['Amount'],
                'payable' => $priceDetails['PassengerFare']['TotalFare']['Amount'],
                'passengerTypeTotal' => [
                    'baseFare' => $priceDetails['PassengerFare']['BaseFare']['Amount'] * $typeCount,
                    'tax' => $tx * $typeCount,
                    'totalFare' => $priceDetails['PassengerFare']['TotalFare']['Amount'] * $typeCount,
                    'commission' => $priceDetails['PassengerFare']['MarkupFare']['Amount'] * $typeCount,
                    'payable' => $priceDetails['PassengerFare']['TotalFare']['Amount'] * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];
        }
        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $price['ItinTotalFare']['BaseFare']['Amount'];
        $newPrice->payable = $price['ItinTotalFare']['TotalFare']['Amount'];
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function extractSupplierCodes()
    {
        ///> find all the airports airlines and airplanes from the response in our database
        if ($this->fromFile == 1) {
            preg_match_all('/"DepartureAirport": {\s+"LocationCode": "(\w+)"/', $this->rawResult['body'], $departureAirportCodes);
            preg_match_all('/"ArrivalAirport": {\s+"LocationCode": "(\w+)"/', $this->rawResult['body'], $arrivalAirportCodes);
            preg_match_all('/"MarketingAirline":{\s+"Code": "(\w+)"/', $this->rawResult['body'], $marketingAirlineCodes);
            preg_match_all('/"OperatingAirline": {\s+"Code": "(\w+)"/', $this->rawResult['body'], $operatingAirlineCodes);
            preg_match_all('/"Equipment": {\s+"AirEquipType": "(\w+)"/', $this->rawResult['body'], $equipments);
        } else {
            foreach ($this->rawResult as $rawResult) {
                preg_match_all('/"DepartureAirport":{"LocationCode":"(\w+)"/', $rawResult['body'], $departureAirportCodes);
                preg_match_all('/"ArrivalAirport":{"LocationCode":"(\w+)"/', $rawResult['body'], $arrivalAirportCodes);
                preg_match_all('/"MarketingAirline":{"Code":"(\w+)"/', $rawResult['body'], $marketingAirlineCodes);
                preg_match_all('/"OperatingAirline":{"Code":"(\w+)"/', $rawResult['body'], $operatingAirlineCodes);
                preg_match_all('/"Equipment":{"AirEquipType":"(\w+)"/', $rawResult['body'], $equipments);
                $airportCodesDep = array_merge($airportCodesDep ?? [], $departureAirportCodes[1] ?? []);
                $airlineCodesArr = array_merge($airlineCodesArr ?? [], $arrivalAirportCodes[1] ?? []);
                $marketCodes = array_merge($marketCodes ?? [], $marketingAirlineCodes[1] ?? []);
                $operateCodes = array_merge($operateCodes ?? [], $operatingAirlineCodes[1] ?? []);
                $equip = array_merge($equip ?? [], $equipments[1] ?? []);
            }
        }
        $this->airlines = $this->loadAirLines(array_unique(array_merge($operateCodes, $marketCodes)));
        $this->airports = $this->loadAirports(array_unique(array_merge($airportCodesDep, $airlineCodesArr)));
        $this->airplanes = $this->loadAirplanes(array_unique($equip));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult[body]' => $this->rawResult['body'], 'airlines' => $airlineCodes[1], 'airports' => array_merge($departureAirportCodes[1], $arrivalAirportCodes[1]), 'airplanes' => $equipments[1], '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality', 'nationalId', 'passport_country', 'passport_number', 'passport_expiry']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function getFields($depends)
    {
        foreach ($depends['legs'] as $legIndex => $leg) {
            $OriginDestinationInformation[] = [
                "DepartureDateTime" => $leg['departure'],
                "OriginLocation" => [
                    "LocationCode" => $leg['origin']['abb']
                ],
                "DestinationLocation" => [
                    "LocationCode" => $leg['destination']['abb']
                ]
            ];
        }
        $fields = [
            "POS" => [
                "UserName" => $depends['username'],
                "Password" => $depends['password']
            ],
            "OriginDestinationInformation" => $OriginDestinationInformation,
            "TravelerInfoSummary" => [
                "AirTravelerAvail" => [
                    [
                        "Code" => "ADT",
                        "Quantity" => $depends['adults']
                    ],
                    [
                        "Code" => "CHD",
                        "Quantity" => $depends['children']
                    ],
                    [
                        "Code" => "INF",
                        "Quantity" => $depends['infants']
                    ]
                ]
            ],
            "Cabin" => ($depends['cabin'] == 'Economy') ? "Y" : "C"
        ];
        return $fields;
    }

    protected function checkCurrency($result)
    {
        if ($result != $this->seller->currency->abb) {
            lugWarning(__METHOD__, [$result]);
            lugError('Currency Mismatch', ['sellerCurrency' => $this->seller->currency, 'result' => $result ?? null]);
            return false;
        }
        return true;
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['AirItinerary']['OriginDestinationOptions'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['AirItinerary']['OriginDestinationOptions'][0]['FlightSegment'][0]['MarketingAirline']['Code'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['AirItineraryPricingInfo'];
    }
}

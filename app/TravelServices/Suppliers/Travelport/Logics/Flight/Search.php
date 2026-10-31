<?php

namespace App\TravelServices\Suppliers\Travelport\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use stdClass;
use Illuminate\Support\Str;


trait Search
{
    use FlightSearch;
    protected $latestSession, $translatedQueryParams, $baggages;
    protected $passengerUuids = [];

    protected function decodeRawResult()
    {
        $xml = preg_replace('/<(\/?)(\w+):(\w+)/', '<$1$3', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($xml);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//Body')), true)[0] ?? [];
    }

    protected function requestGenerator()
    {
        foreach (['adults', 'children', 'infants'] as $type) {
            $count = (int)$this->modifiedPassengersTypeCount[$type];
            for ($i = 0; $i < $count; $i++) {
                $this->passengerUuids[$type][$i] = Str::uuid()->toString();
            }
        }
        // $this->LugTheResponse = 1;
        $this->translatequeryParams = $this->translateQueryParams();
        $auth = $this->seller->api->configs;
        $depends = $this->translatequeryParams + $auth + ['passengerUuids' => $this->passengerUuids];
        $this->call = [
            'fields' => $fields = $this->minifyXML(view('Travelport.FlightSearch', $depends)->render()),
            'url' => $this->seller->api->configs['url'],
            'headers' => [
                "Content-length: " . strlen($fields),
            ],
            'timeout' => 15,
            'apiMethod' => 'Search'
        ];

        return $this->action();
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded']['LowFareSearchRsp'] ?? '';
        if (empty($rawResults)) {
            $this->addError('Empty Items');
            return;
        }
        if (!$this->checkCurrency($this->rawResult['decoded'])) {
            $this->addError('Currency Mismatch');
            return;
        }
        $this->saveRawResponse();

        $this->extractSupplierCodes();

        $this->items = $this->itinerary($this->recommendation($rawResults));
    }

    protected function recommendation($rawResults)
    {
        $airSegmentList = $this->extractAirSegmentList();
        $fareInfoList = collect($rawResults['FareInfoList']['FareInfo']);
        $flightDetailsList = collect($rawResults['FlightDetailsList']['FlightDetails']);
        $this->repelSingleMode($rawResults['AirPricingSolution']);
        foreach ($rawResults['AirPricingSolution'] as $itineraryIndex => $itinerary) {
            $this->repelSingleMode($itinerary['Journey']);
            foreach ($itinerary['Journey'] as $leg) {
                $this->repelSingleMode($leg['AirSegmentRef']);
                foreach ($leg['AirSegmentRef'] as $segment) {
                    $segmentInfo = $airSegmentList->whereStrict('@attributes.Key', $segment['@attributes']['Key'])->first();
                    $segmentInfo['flighDetails'] = $flightDetailsList->whereStrict('@attributes.Key', $segmentInfo['@attributes']['FlightDetailsRef']['Key'])->first();
                    $legIndex = (int) $segmentInfo['@attributes']['Group'];  // Casting to integer
                    $rawResults['AirPricingSolution'][$itineraryIndex]['legs'][$legIndex]['segments'][] = $segmentInfo;
                }
            }
            $this->repelSingleMode($itinerary['AirPricingInfo']);
            foreach ($itinerary['AirPricingInfo'] as $airPricingInfoIndex => $airPricingInfo) {
                $this->repelSingleMode($airPricingInfo['BookingInfo']);
                foreach ($airPricingInfo['BookingInfo'] as $bookingInfo) {
                    $fareInfo =  $fareInfoList->whereStrict('@attributes.Key', $bookingInfo['@attributes']['FareInfoRef'])->first();
                    $segmentRef = $bookingInfo['@attributes']['SegmentRef'];
                    // Find and update the segment in 'legs' with the fare info
                    foreach ($rawResults['AirPricingSolution'][$itineraryIndex]['legs'] as $index => $leg) {
                        foreach ($leg['segments'] as $segIndex => $segment) {
                            if ($segment['@attributes']['Key'] === $segmentRef) {
                                $this->repelSingleMode($airPricingInfo['PassengerType']);
                                $passengerType = $airPricingInfo['PassengerType'][0]['@attributes']['Code'];
                                $rawResults['AirPricingSolution'][$itineraryIndex]['legs'][$index]['segments'][$segIndex]['fareInfo'][$passengerType] = $fareInfo;
                                $rawResults['AirPricingSolution'][$itineraryIndex]['legs'][$index]['segments'][$segIndex]['bookingInfo'][$passengerType] = $bookingInfo;
                            }
                        }
                    }
                    if (!str_contains(strtoupper($bookingInfo['@attributes']['CabinClass']), strtoupper($this->queryParams['cabin']))) { ///> this way we'll include Premium options in the results
                        unset($rawResults['AirPricingSolution'][$itineraryIndex]);
                        continue 3;
                    }
                }
            }
        }

        return $rawResults['AirPricingSolution'];
    }

    protected function extractSupplierCodes()
    {
        preg_match_all('/ Carrier="(\w+)"/', $this->rawResult['body'], $airlineCodes);
        preg_match_all('/ OperatingCarrier="(\w+)"/', $this->rawResult['body'], $oAirlineCodes);
        preg_match_all('/Origin="(\w+)"/',  $this->rawResult['body'],  $departureAirportCodes);
        preg_match_all('/Destination="(\w+)"/',  $this->rawResult['body'], $arrivalAirportCodes);
        preg_match_all('/Equipment="(\w+)"/',  $this->rawResult['body'], $equipments);

        $this->airlines = $this->loadAirLines(array_unique(array_merge($airlineCodes[1], $oAirlineCodes[1])));
        $this->airports = $this->loadAirports(array_unique(array_merge($departureAirportCodes[1], $arrivalAirportCodes[1])));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments[1]));
        // lugInfo('extractSupplierCodes$this->airlines', [$this->airlines, $airlineCodes, $vAirlineCodes, $mairlineCodes]);

    }

    protected function checkCurrency($result)
    {
        $currency = $result['LowFareSearchRsp']['@attributes']['CurrencyType'] ?? $this->seller->currency->abb;
        if ($currency != $this->seller->currency->abb) {
            lugError('Currency Mismatch', ['sellerCurrency' => $this->seller->currency, 'Currency' =>  $currency ?? null]);
            return false;
        }
        return true;
    }

    protected function legMapper($leg)
    {
        $segments = $leg['segments'];
        $cabin = $this->queryParams['cabin'];
        $newLeg = new stdClass();
        $newLeg->airlines = $newLeg->segments = $legFlightNumbers = $resBookDesigCodes = $segmentBrandsIdes = $segmentBrands = $segmentResBookDesigCodes = $segmentCapacities = $capacities = [];
        $newLeg->stops = count($segments) - 1;
        foreach ($segments as $key => $segment) {
            $operatingAirline =  !empty($this->airlines[$segment['@attributes']['CodeshareInfo']['OperatingCarrier']]) ? $this->airlines[$segment['@attributes']['CodeshareInfo']['OperatingCarrier']] : $segment['@attributes']['Carrier'] ?? null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['@attributes']['CodeshareInfo']['OperatingCarrier'] ?? $segment['@attributes']['Carrier'],
                    'data_reference' => types('supplierDataReference', 'Travelport'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }

            $marketingAirline = $this->airlines[$segment['@attributes']['Carrier']] ?? null;
            if (empty($marketingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['@attributes']['Carrier'],
                    'data_reference' => types('supplierDataReference', 'Travelport'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }
            foreach ($segment['bookingInfo'] as $bookingInfo) {
                $cabin = ['Economy' => 'Economy', 'Premium Economy' => 'Premium Economy', 'Business' => 'Business', 'First' => 'First', 'Premium First' => 'Premium First'][$bookingInfo['@attributes']['CabinClass']] ?? null;
                if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) {
                    // lugWarning('TravelPort cabin mismatch', ['segCabins' => $segment['bookingInfo'], 'queryParams' => $this->queryParams['cabin']]);
                    return false;
                }
            }
            $departureCity = $this->airports[$segment['@attributes']['Origin']]['city']['title'] ?? '';
            $departureDateTime = $this->humanDateTime($segment['@attributes']['DepartureTime']);
            $departureAirport = $this->airports[$segment['@attributes']['Origin']] ?? '';
            if (empty($departureAirport)) {
                // lugWarning('DepartureAirport not found ' . ($segment['DestinationAirport'] ?? ''), [$segment]);
                return false;
            }

            $departureTerminal = $segment['flighDetails']['@attributes']['OriginTerminal'] ?? '-';
            $arrivalCity = $this->airports[$segment['@attributes']['Destination']]['city']['title'] ?? '';
            $arrivalDateTime = $this->humanDateTime($segment['@attributes']['ArrivalTime']);
            $arrivalAirport = $this->airports[$segment['@attributes']['Destination']] ?? '';
            if (empty($arrivalAirport)) {
                // lugWarning('ArrivalAirport not found ' . ($segment['DestinationAirport'] ?? ''), [$segment]);
                return false;
            }

            $baggage = '';
            $arrivalTerminal = $segment['flighDetails']['@attributes']['DestinationTerminal'] ?? '-';
            if (isset($segment['fareInfo'])) {
                $baggage = $this->baggageModifier($segment);
            }

            $duration = $segment['@attributes']['FlightTime'];
            $hours = intdiv($duration, 60);
            $remainingMinutes = $duration % 60;
            // Format as HH:MM:SS
            $formattedDuration = sprintf('%02d:%02d:00', $hours, $remainingMinutes);
            $durations[] = $formattedDuration;

            if (empty($segments[$key + 1])) {
                $dateTime = $segment['@attributes']['ArrivalTime'];
            } else {
                $dateTime = ($segments[$key + 1]['@attributes']['DepartureTime']);
            }

            $durations[] = $afterFlight = $this->between2Time($segment['@attributes']['ArrivalTime'], $dateTime);

            $legFlightNumbers[] = $flightNumber = $segment['@attributes']['FlightNumber'];
            foreach ($segment['bookingInfo'] as $bookingInfo) {
                $segmentResBookDesigCodes[] =  $bookingInfo['@attributes']['BookingCode'];
                $segmentCapacities[] = $bookingInfo['@attributes']['BookingCount'];
            }
            if (!empty($segmentResBookDesigCodes) && !array_unique($segmentResBookDesigCodes) == 1) {
                // lugWarning(' resBookDesigCodes are not same for this segment ', [$resBookDesigCodes, $segment]);
                return false;
            };
            if (!array_unique($segmentCapacities) == 1) {
                // lugWarning('capacities are not same for this segment', [$capacities, $segment]);
                return false;
            };

            $resBookDesigCodes[] = $resBookDesigCode = $segmentResBookDesigCodes[0];
            $capacities[] = $capacity = (int)$segmentCapacities[0] ?? null;


            $airplane = $this->airplanes[$segment['@attributes']['Equipment']]['title'] ?? $segment['@attributes']['Equipment'];
            $newLeg->airlines[] = $marketingAirline['id'];
            $newLeg->segments[$key] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' =>  $segment['@attributes']['DepartureTime'],
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arrivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' =>  $segment['@attributes']['ArrivalTime'],
                ],
                'airline' => $marketingAirline,
                'operatingAirline' =>  $operatingAirline,
                'cabin' => $this->queryParams['cabin'],
                'luggage' =>  $baggage ?? '--',
                'hand_luggage' => '',
                'airplane' => $airplane,
                'duration' => $duration,
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
            'luggage' => $baggage,
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' =>  $this->sumDurations($durations),
            'connections' => count($segments) - 1,
            'ticket_type' => 'system',
            'flight_number' => implode('/', $legFlightNumbers),
            'after_flight_stop' => '-',
            'resBookDesigCode' => implode('/', $resBookDesigCodes),
            'capacity' => empty($capacities) ? '-' : min($capacities),
            'refundable' => '', ////////////////> exists in the response
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = implode('/', $resBookDesigCodes);
        return $newLeg;
    }

    protected function priceMapper($price)
    {
        $airPrcingInfo = $price['AirPricingInfo'];
        $newPrice = new stdClass();
        $sumBaseFare = $sumTotalFare = 0;
        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;

        $this->repelSingleMode($airPrcingInfo);
        foreach ($airPrcingInfo as $priceDetails) {
            $passengerTypeData = null;
            $this->repelSingleMode($priceDetails['PassengerType']);
            $passengerTypeData = $priceDetails['PassengerType'][0]['@attributes']['Code'];

            $passengerType = ['ADT' => 'adult', 'CHD' => 'child', 'INF' => 'infant', 'CNN' => 'child'][$passengerTypeData];
            $typeCount = count($priceDetails['PassengerType']);

            // Check if requested passenger count matches result
            if ($typeCount != ${$passengerType . 'Count'}) {
                lugError($passengerType . ' count mismatch', ['passengersTypeCount' => $this->passengersTypeCount, 'result count' => $priceDetails['PassengerType']]);
            } else {
                // Mark passenger type as exists
                ${$passengerType . 'Count'} = 'exists';
            }

            if (!empty($priceDetails['@attributes']['EquivalentBasePrice'])) {
                $bacePriceWithCurrency = $priceDetails['@attributes']['EquivalentBasePrice'];
            } else {
                $bacePriceWithCurrency = $priceDetails['@attributes']['BasePrice'];
            }
            $totalPriceWithCurrency = $priceDetails['@attributes']['TotalPrice'];
            $taxWithCurrency = $priceDetails['@attributes']['Taxes'];
            preg_match('/([A-Z]+)(\d+\.\d+)/', $bacePriceWithCurrency, $baseMatch);
            preg_match('/([A-Z]+)(\d+\.\d+)/', $totalPriceWithCurrency, $totalMatch);
            preg_match('/([A-Z]+)(\d+\.\d+)/', $taxWithCurrency, $taxMatch);
            if (!$this->validateCurrencyMatch([$baseMatch[1], $baseMatch[1], $totalMatch[1]])) {
                lugError('currency mistake for this flight', ['price info response' => $priceDetails, 'currency' => $this->seller->currency->abb]);
                return false;
            }
            $baseFare = $baseMatch[2];
            $totalFare = $totalMatch[2];
            $tax = $taxMatch[2];
            $newPriceBreakDowns[$passengerType] = [
                'baseFare' => $baseFare,
                'tax' => $tax,
                'totalFare' =>  $totalFare,
                'commission' => "0",
                'payable' => $totalFare,
                'passengerTypeTotal' => [
                    'baseFare' => $baseFare * $typeCount,
                    'tax' => $tax * $typeCount,
                    'totalFare' => $totalFare * $typeCount,
                    'commission' => 0,
                    'payable' => $totalFare * $typeCount,
                    'passengersCount' => $typeCount,
                ],
            ];
        }

        // Check if all passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            return false;
        }
        if (!empty($price['@attributes']['EquivalentBasePrice'])) {
            $sumBasePrice = $price['@attributes']['EquivalentBasePrice'];
        } else {
            $sumBasePrice = $price['@attributes']['BasePrice'];
        }
        preg_match('/([A-Z]+)(\d+\.\d+)/', $sumBasePrice, $sumBaseMatch);
        preg_match('/([A-Z]+)(\d+\.\d+)/', $price['@attributes']['TotalPrice'], $sumTotalMatch);
        if (!$this->validateCurrencyMatch([$sumBaseMatch[1], $sumTotalMatch[1]])) {
            lugError('currency mistake for this flight', ['price info response' => $price, 'currency' => $this->seller->currency->abb]);
            return false;
        }
        $sumBaseFare = $sumBaseMatch[2];
        $sumTotalFare = $sumTotalMatch[2];

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $sumBaseFare;
        $newPrice->payable = $sumTotalFare;
        $newPrice->diff = $newPrice->payable - $newPrice->baseFare;

        return $newPrice;
    }

    protected function validateCurrencyMatch($matches)
    {
        foreach ($matches as $match) {
            if ($this->seller->currency->abb !== $match) {
                return false;
            }
        }
        return true;
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = false;
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = ['price' => false];
        $info->extra = [
            'requestInfo' => $itinerary['legs'],
            'traceId' => $this->rawResult['decoded']['LowFareSearchRsp']['@attributes']['TraceId'],
            'passengerUuids' => $this->passengerUuids
        ];

        return $info;
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['phone', 'text_area', 'email'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'passport_expiry', 'passport_number', 'nationality']
        ];
        $data = $this->addPassengerFormItem($items);
        return $data;
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['legs'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['legs'][0]['segments'][0]['@attributes']['Carrier'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return ['AirPricingInfo' => $itinerary['AirPricingInfo'], '@attributes' => $itinerary['@attributes']];
    }

    protected function extractAirSegmentList()
    {
        $xml = preg_replace('/<(\/?)(\w+):(\w+)/', '<$1$3', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($xml);
        $lowFareSearchRsp = $xml->xpath('//LowFareSearchRsp')[0];
        $data = [];

        foreach ($lowFareSearchRsp->xpath('AirSegmentList/AirSegment') as $airSegment) {
            $codeshareInfo = $airSegment->CodeshareInfo;
            $data[] = [
                '@attributes' => [
                    'Key' => (string) $airSegment['Key'],
                    'Group' => (string) $airSegment['Group'],
                    'Carrier' => (string) $airSegment['Carrier'],
                    'FlightNumber' => (string) $airSegment['FlightNumber'],
                    'Origin' => (string) $airSegment['Origin'],
                    'Destination' => (string) $airSegment['Destination'],
                    'DepartureTime' => (string) $airSegment['DepartureTime'],
                    'ArrivalTime' => (string) $airSegment['ArrivalTime'],
                    'FlightTime' => (string) $airSegment['FlightTime'],
                    'Distance' => (string) $airSegment['Distance'] ?? '',
                    'ETicketability' => (string) $airSegment['ETicketability'] ?? '',
                    'Equipment' => (string) $airSegment['Equipment'] ?? '',
                    'ChangeOfPlane' => (string) $airSegment['ChangeOfPlane'] ?? '',
                    'ParticipantLevel' => (string) $airSegment['ParticipantLevel'] ?? '',
                    'LinkAvailability' => (string) $airSegment['LinkAvailability'] ?? '',
                    'PolledAvailabilityOption' => (string) $airSegment['PolledAvailabilityOption'] ?? '',
                    'OptionalServicesIndicator' => (string) $airSegment['OptionalServicesIndicator'] ?? '',
                    'AvailabilitySource' => (string) $airSegment['AvailabilitySource'] ?? '',
                    'AvailabilityDisplayType' => (string) $airSegment['AvailabilityDisplayType'] ?? '',
                    'CodeshareInfo' => [
                        'OperatingCarrier' => (string) $codeshareInfo['OperatingCarrier'] ?? '',
                        'OperatingFlightNumber' => (string) $codeshareInfo['OperatingFlightNumber'] ?? '',
                        'Name' => trim((string)$codeshareInfo) // Clean up the name text
                    ],
                    'AirAvailInfo' => [
                        'ProviderCode' => (string)$airSegment->AirAvailInfo['ProviderCode'] ?? '',
                    ],
                    'FlightDetailsRef' => [
                        'Key' => (string)$airSegment->FlightDetailsRef['Key'] ?? '',
                    ],
                ]

            ];
        }
        $airSegmentList = collect($data);
        return $airSegmentList;
    }

    protected function baggageModifier($segment)
    {
        $text = '';
        foreach ($segment['fareInfo'] as $fareInfo) {
            if (!empty($fareInfo['BaggageAllowance']['MaxWeight'])) {
                $text = "$text {$fareInfo['BaggageAllowance']['MaxWeight']['@attributes']['Value']} {$fareInfo['BaggageAllowance']['MaxWeight']['@attributes']['Unit']}/{$fareInfo['@attributes']['PassengerTypeCode']} -";
            } elseif (!empty($fareInfo['BaggageAllowance']['NumberOfPieces'])) {
                $text = "$text {$fareInfo['BaggageAllowance']['NumberOfPieces']} PC/{$fareInfo['@attributes']['PassengerTypeCode']} -";
            }
        }
        return rtrim(ltrim($text), '-');
    }
}

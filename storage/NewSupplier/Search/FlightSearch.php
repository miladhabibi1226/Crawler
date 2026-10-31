<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use stdClass;

trait Search
{
    use FlightSearch;

    protected $latestSession;

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/DummyName/FlightSearch.json'));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Request generator unsuccessful");
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        // $this->LugTheResponse = 1;
        $auth = $this->seller->api->configs;
        $queryParams = $this->translateQueryParams();
        $depends = $auth + $queryParams;
        lugInfo("DummyName Search Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' =>  $auth['url'],
            'headers' => [],
            'timeout' => 20
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->extractSupplierCodes();

        $rawResults = $this->rawResult['decoded'];

        if (empty($rawResults)) {
            $this->addError('Empty pricedItineraries');
            return;
        }

        $this->items = $this->itinerary($rawResults);
        lugInfo('S>>>> DummyName flight search Extractor items - ' . $this->seller->alias, ['supplier raw response items count' => count($rawResults), 'Inserted count' => count($this->items), 'new airline Suppliers' => $this->airlineSuppliers]);
    }

    protected function itinerary($rawResults)
    {
        foreach ($rawResults as $itinerary) {
            $item = [];
            $this->airlineFrequency($itinerary['ValidatingAirlineCode']);
            $item['service'] = $this->legModifier($itinerary['OriginDestinationOptions'], $itinerary['ValidatingAirlineCode']);
            if ($item['service'] == false) {
                continue;
            }
            $item['price'] = $this->priceModifier($itinerary['AirItineraryPricingInfo'], $this->getPricingConditions($item['service']));
            $item['sellerInfo'] = $this->sellerInfoModifier($itinerary);
            $items[] = $item;
        }
        return $items ?? [];
    }

    protected function sellerInfoModifier($itinerary)
    {
        return [
            'onholdable' => $this->onholdable,
            'passengersForm' => $this->getPassengersForm(),
            'afters' => ['fareRules' => false]
        ];
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass();

        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;

        $breakDowns = $price['PTC_FareBreakdowns']['PTC_FareBreakdown'];
        foreach ($breakDowns as $priceDetails) {
            $passengerType = ['ADT' => 'adult', 'CHD' => 'child', 'INF' => 'infant'][$priceDetails['PassengerTypeQuantity']['Code']];
            $typeCount = $priceDetails['PassengerTypeQuantity']['Quantity'];

            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                lugError($passengerType . ' count mismatch', ['passengersTypeCount' => $this->passengersTypeCount, 'result count' => $priceDetails['PassengerTypeQuantity']]);
                $this->addError($passengerType . ' count mismatch');
            } else {
                ///> to check later that all the requested passengers types are available in th eresult
                ${$passengerType . 'Count'} = 'exists';
            }

            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['BaseFare'],
                'tax' => $tx = $priceDetails['Taxes'] + $priceDetails['ServiceTax'],
                'totalFare' => $priceDetails['TotalFare'],
                'commission' => $priceDetails['Commission'],
                'payable' => $priceDetails['TotalFare'],
                'passengerTypeTotal' => [
                    'baseFare' => $priceDetails['BaseFare'] * $typeCount,
                    'tax' => $tx * $typeCount,
                    'totalFare' => $priceDetails['TotalFare'] * $typeCount,
                    'commission' => $priceDetails['Commission'] * $typeCount,
                    'payable' => $priceDetails['TotalFare'] * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];
        }

        ///> checking if all the passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            $this->addError('passengers type mismatch');
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $price['ItinTotalFare']['BaseFare'];
        $newPrice->payable = $price['ItinTotalFare']['TotalFare'];
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function legMapper($leg)
    {
        $newLeg = new stdClass();
        $newLeg->segments = $newLeg->airlines = $legFlightNumbers = $resBookDesigCodes = $capacities = [];
        $segments = $leg['FlightSegment'];
        $newLeg->stops = count($segments) - 1;
        foreach ($segments as $key => $segment) {
            $operatingAirline = $this->airlines[$segment['OperatingAirline']] ?? Null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['OperatingAirline'],
                    'data_reference' => types('supplierDataReference', 'DummyName')
                ];
                return false;
            }
            $marketingAirline = $this->airlines[$segment['MarketingAirline']] ?? Null;
            if (empty($marketingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['MarketingAirline'],
                    'data_reference' => types('supplierDataReference', 'DummyName')
                ];
                return false;
            }
            $cabin = $segment['cabin'];
            if (strtoupper($cabin) != strtoupper($this->queryParams['cabin'])) {
                lugWarning(
                    'cabin mismatch',
                    // ['request' => $this->queryParams['cabin'], 'result' => $cabin, 'segment' => $segment]
                );
                return false;
            }

            $departureAirport = $this->airports[$segment['DepartureAirport']] ?? '';
            if (empty($departureAirport)) {///> means the Airport is not in database
                lugWarning('DepartureAirport not found ' . ($segment['DepartureAirportLocationCode'] ?? ''), []);
                return false;
            }
            $arrivalAirport = $this->airports[$segment['ArrivalAirport']] ?? '';
            if (empty($arrivalAirport)) {///> means the Airport is not in database
                lugWarning('ArrivalAirport not found ' . ($segment['ArrivalAirportLocationCode'] ?? ''), []);
                return false;
            }
            $departureCity = $this->airports[$segment['DepartureAirport']]['city']['title'] ?? $segment['DepartureAirport'];
            $arrivalCity = $this->airports[$segment['ArrivalAirport']]['city']['title'] ?? $segment['ArrivalAirport'];
            $departureDateTime = $this->humanDateTime($segment['DepartureDateTime']);
            $arrivalDateTime = $this->humanDateTime($segment['ArrivalDateTime']);
            $departureTerminal = $segment['DepartureAirport']['Terminal'] ?? '-';
            $arivalTerminal = $segment['ArrivalAirport']['Terminal'] ?? '-';

            $baggage = empty($segment['Baggages']) ? null : "{$this->baggages[$segment['Baggages']['Baggage']['Index']]}/{$segment['Baggages']['Baggage']['Type']}";
            $airplane = $this->airplanes[$segment['Equipment']]['title'] ?? $segment['Equipment'];
            $duration = $this->humanDateTime($segment['FlightDuration']);
            $afterFlight = $this->between2Time(
                $segment['ArrivalDateTime'],
                $segments[$key + 1]['DepartureDateTime'] ?? $segment['ArrivalDateTime']
            );
            $legFlightNumbers[] = $flightNumber = $segment['FlightNumber'];
            $resBookDesigCodes[] = $resBookDesigCode = $segment['ResBookDesigCode'];
            $capacities[] = $capacity = (int) $segment['SeatsRemaining'] ?? null;

            $newLeg->airlines[] = $marketingAirline['id'];
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
                'duration' => $duration['time'],
                'ticket_type' => 'system',
                'flight_number' => $flightNumber,
                'after_flight_stop' => $afterFlight,
                'resBookDesigCode' => $resBookDesigCode,
                'capacity' => $capacity
            ];
        }
        $legDuration = substr($leg['ElapsedTime'], 0, 2) . ':' . substr($leg['ElapsedTime'], 2);
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
        return $newLeg;
    }

    protected function extractSupplierCodes()
    {
        ///> find all the airports airlines and airplanes from the response in our database
        preg_match_all('/DepartureAirport LocationCode="(\w+)"/', $this->rawResult['body'], $departureAirportCodes);
        preg_match_all('/ArrivalAirport LocationCode="(\w+)"/', $this->rawResult['body'], $arrivalAirportCodes);
        preg_match_all('/MarketingAirline Code="(\w+)"/', $this->rawResult['body'], $marketingAirlineCodes);
        preg_match_all('/OperatingAirline Code="(\w+)"/', $this->rawResult['body'], $operatingAirlineCodes);
        preg_match_all('/Equipment AirEquipType="(\w+)"/', $this->rawResult['body'], $equipments);
        $this->airlines = $this->loadAirLines(array_unique(array_merge($operatingAirlineCodes[1], $marketingAirlineCodes[1])));
        $this->airports = $this->loadAirports(array_unique(array_merge($departureAirportCodes[1], $arrivalAirportCodes[1])));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments[1]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult[body]' => $this->rawResult['body'], 'airlines' => $airlineCodes[1], 'airports' => array_merge($departureAirportCodes[1], $arrivalAirportCodes[1]), 'airplanes' => $equipments[1], '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality', 'nationalId', 'passport_number', 'passport_expiry', 'passport_country']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function getFields($depends)
    {
        $fields = [];
        return $fields;
    }
}

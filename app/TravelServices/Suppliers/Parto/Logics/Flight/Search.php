<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use App\TravelServices\Suppliers\Parto\Logics\Search as PartoSearch;
use stdClass;

trait Search
{
    protected  $isDomesticFlight;
    protected $onholdable = true;
    use FlightSearch, PartoSearch {
        PartoSearch::canBedone insteadof FlightSearch;
        FlightSearch::getContent insteadof PartoSearch;
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded']['PricedItineraries'] ?? $this->callException('empty priced itineraries');
        $this->saveRawResponse();
        $this->extractSupplierCodes();
        $this->isDomesticFlight = $this->isDomestic();
        $this->items = $this->itinerary($rawResults);
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = $this->onholdable;
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = ['revalidate' => false];
        $info->extra = [
            'isDomesticFlight' => $this->isDomesticFlight,
            'FareSourceCode' => $itinerary['FareSourceCode']
        ];
        return $info;
    }

    protected function legMapper($leg)
    {
        $segments = $leg['FlightSegments'];
        $this->repelSingleMode($segments);
        $cabin = $this->queryParams['cabin'];
        $newLeg = new stdClass();
        $newLeg->airlines = $newLeg->segments = $legFlightNumbers = $resBookDesigCodes = $capacities = [];
        $newLeg->stops = count($segments) - 1;
        foreach ($segments as $key => $segment) {
            $marketingAirline = $this->airlines[$segment['MarketingAirlineCode']] ?? null;
            if (empty($marketingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['MarketingAirlineCode'],
                    'data_reference' => types('supplierDataReference', 'Parto'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }

            $operatingAirline = $this->airlines[$segment['OperatingAirline']['Code']] ?? null;
            if (empty($operatingAirline)) {
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['OperatingAirline']['Code'],
                    'data_reference' => types('supplierDataReference', 'Parto'),
                ];
                return false;
            }

            $cabin = [1 => 'Economy', 2 => 'Economy', 3 => 'Business', 4 => 'Premium Business', 5 => 'First', 6 => 'Premium First', 100 => 'Default'][(int)$segment['CabinClassCode']] ?? null; ///> we're counting Premium Economy(2) as Economy
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
            $baggage = $segment['Baggage'] ?? "--";
            $airplane = $this->airplanes[$segment['OperatingAirline']['Equipment']]['title'] ?? $segment['OperatingAirline']['Equipment'];
            $duration = $segment['JourneyDuration'];
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
        $legDuration = intval(($leg['JourneyDurationPerMinute'] + $leg['ConnectionTimePerMinute']) / 60) . ":" . (($leg['JourneyDurationPerMinute'] + $leg['ConnectionTimePerMinute']) % 60);
        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[count($newLeg->segments) - 1]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $this->queryParams['cabin'],
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

    protected function priceMapper($price)
    {
        if ($price['ItinTotalFare']['Currency'] != $this->seller->currency->abb) {
            lugError('Currency mismatch', ['seller price' => $this->seller->currency->abb, 'result price' => $price['ItinTotalFare']['Currency']]);
            $this->callException('Currency mismatch');
        }

        if ($price['FareType'] == 4) {
            $this->onholdable = false;
        } else {
            $this->onholdable = true;
        }

        $newPrice = new stdClass();

        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;

        foreach ($price['PtcFareBreakdown'] as $priceDetails) {
            $passengerType = [1 => 'adult', 2 => 'child', 3 => 'infant'][$priceDetails['PassengerTypeQuantity']['PassengerType']];
            $typeCount = $priceDetails['PassengerTypeQuantity']['Quantity'];

            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                // lugError($passengerType . ' count mismatch', ['passengersTypeCount' => $this->passengersTypeCount, 'result count' => $priceDetails['PassengerTypeQuantity']]);
            } else {
                ///> to check later that all the requested passengers types are available in the result
                ${$passengerType . 'Count'} = 'exists';
            }

            $tax = array_sum(array_column($priceDetails['PassengerFare']['Taxes'], 'Amount'));
            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['PassengerFare']['BaseFare'],
                'tax' => $tx = $tax + $priceDetails['PassengerFare']['ServiceTax'],
                'totalFare' => $priceDetails['PassengerFare']['TotalFare'],
                'commission' => $priceDetails['PassengerFare']['Commission'], ///> in parto's result commission is already deducted from total
                'payable' => $priceDetails['PassengerFare']['TotalFare'], ///> acording to the docs, total fare is the 'Net Payable'
                'passengerTypeTotal' => [
                    'baseFare' => $priceDetails['PassengerFare']['BaseFare'] * $typeCount,
                    'tax' => $tx * $typeCount,
                    'totalFare' => $priceDetails['PassengerFare']['TotalFare'] * $typeCount,
                    'commission' => $priceDetails['PassengerFare']['Commission'] * $typeCount,
                    'payable' => $priceDetails['PassengerFare']['TotalFare'] * $typeCount,
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
        $newPrice->baseFare = $price['ItinTotalFare']['BaseFare'];
        $newPrice->payable = $price['ItinTotalFare']['TotalFare'];
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function extractSupplierCodes()
    {
        preg_match_all('/"DepartureAirportLocationCode":"(\w+)"/', $this->rawResult['body'], $departureAirportCodes);
        preg_match_all('/"ArrivalAirportLocationCode":"(\w+)"/', $this->rawResult['body'], $arrivalAirportCodes);
        preg_match_all('/"MarketingAirlineCode":"(\w+)"/', $this->rawResult['body'], $mairlineCodes);
        preg_match_all('/"Code":"(\w+)"/', $this->rawResult['body'], $airlineCodes);
        preg_match_all('/"ValidatingAirlineCode":"(\w+)"/', $this->rawResult['body'], $vAirlineCodes);
        preg_match_all('/"Equipment":"(\w+)"/', $this->rawResult['body'], $equipments);
        $this->airlines = $this->loadAirLines(array_unique(array_merge($airlineCodes[1], $vAirlineCodes[1], $vAirlineCodes[1])));
        $this->airports = $this->loadAirports(array_unique(array_merge($departureAirportCodes[1], $arrivalAirportCodes[1])));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments[1]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['AirportCodes' => array_unique(array_merge($departureAirportCodes[1], $arrivalAirportCodes[1])), 'AirlineCode' => array_unique(array_merge($airlineCodes[1], $vAirlineCodes[1], $vAirlineCodes[1])), '$equipments' => $equipments, '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'passport_expiry', 'passport_number', 'passport_country', 'nationality']
        ];
        $data = $this->addPassengerFormItem($items);

        if ($this->isDomesticFlight) {
            $data = $this->addPassengerFormItem(['all' => ['nationalId']], $data);
        }
        return $data;
    }

    protected function getFields()
    {
        foreach ($this->translatedQueryParams['legs'] as $legIndex => $leg) {
            $OriginDestinationInformation[] = [
                "DepartureDateTime" => $leg['departure'],
                "OriginLocationCode" => $leg['origin']['abb'],
                "OriginType" => ($leg['origin']['isCity'] == "true") ? 1 : 2,
                "DestinationLocationCode" => $leg['destination']['abb'],
                "DestinationType" => ($leg['destination']['isCity'] == "true") ? 1 : 2
            ];
        }
        $data = [
            "PricingSourceType" => 0,
            "RequestOption" => 3,
            "SessionId" => $this->latestSession,
            "AdultCount" => $this->translatedQueryParams['adults'],
            "ChildCount" => $this->translatedQueryParams['children'],
            "InfantCount" => $this->translatedQueryParams['infants'],
            "TravelPreference" => [
                "CabinType" => ['Business' => 3, 'First' => 5, 'Economy' => 1][$this->translatedQueryParams['cabin']],
                "MaxStopsQuantity" => 0,
                "AirTripType" => $this->isOneWaySearch ? 1 : 2
            ],
            "OriginDestinationInformations" => $OriginDestinationInformation
        ];
        return $data;
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['OriginDestinationOptions'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['ValidatingAirlineCode'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['AirItineraryPricingInfo'];
    }
}

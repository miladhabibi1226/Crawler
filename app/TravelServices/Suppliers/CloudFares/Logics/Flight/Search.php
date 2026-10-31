<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

use App\TravelServices\Logics\Flight\OpenReturn;
use App\TravelServices\Logics\Flight\Search as FlightSearch;
use Carbon\Carbon;
use stdClass;
use Illuminate\Support\Str;

trait Search
{
    use FlightSearch, OpenReturn {
        OpenReturn::getContent insteadof FlightSearch;
        OpenReturn::getSystemExceptions insteadof FlightSearch;
        OpenReturn::checkServiceInfoValidity as OpenReturnCheckServiceInfoValidity;
        FlightSearch::checkServiceInfoValidity as mainCheckServiceInfoValidity;
    }
    protected $fareRuleInfo,  $translatedQueryParams, $baggageInfo  = [], $openReturnDateRange = 5;

    public function canBeDone()
    {
        if (!$this->findOrCreateSupplierSession()) {
            return $this->addError('can not get session');
        }
        $this->translatedQueryParams = $this->translateQueryParams();
    }

    protected function getFields($depends, $openReturnDate = null)
    {
        $airTripType = match ($this->translatedQueryParams['tripType']) {
            'oneWay' => 0,
            'roundTrip' => 1,
            'openReturn' => 1,
            'multiDestination' => 2
        };
        foreach ($depends['legs'] as $legIndex => $leg) {
            $legData = [
                "from" => $leg['origin']['abb'],
                "to" => $leg['destination']['abb'],
            ];
            if (!empty($openReturnDate) && $legIndex == 1) {
                $legData["date"] = $openReturnDate;
            } else {
                $legData["date"] = $leg['departure'];
            }
            $legs[] = $legData;
        }
        $data = [
            'legs' => $legs,
            'search_type' => (string)$airTripType,
            'allowed_airlines' => 'all',
            'disallowed_airlines' => 'none',
            "adt" => (int) $this->translatedQueryParams['adults'],
            "chd" => (int) $this->translatedQueryParams['children'],
            "inf" => (int) $this->translatedQueryParams['infants'],
            "class" => "all",
            "cabin" => $this->translatedQueryParams['cabin'],
            "trace_id" =>  Str::random(16),
            "mark_up" => null

        ];
        return $data;
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded']['data']['search_flight'] ?? [];
        if (empty($rawResults)) {
            return $this->addError('Empty SearchResults');
        }
        if (!$this->checkCurrencyCode()) {
            return $this->addError('currency mismatch');
        }
        $this->saveRawResponse();
        $this->extractSupplierCodes();
        $this->items = $this->itinerary($rawResults);
    }

    protected function checkCurrencyCode()
    {
        if (($this->rawResult['decoded']['data']['search_flight'][0]['price']['currency'] ?? null) != $this->seller->currency->abb) {
            lugError('currency mismatch', ['result' => $this->rawResult['decoded']['data']['search_flight'][0]['price']['currency'] ?? null, 'seller' => $this->seller->currency->abb]);
            return false;
        }
        return true;
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = true;
        $info->afterSearch = ['brands' => false, 'fareRules' => false];
        $info->beforeBook = ['price' => false];
        $info->addOns = ['baggages' => false];
        $info->extra = [
            'trace_id' => $itinerary['trace_id'],
            'result_id' => $itinerary['result_id'],
            'fareRuleInfo' => $this->fareRuleInfo
        ];
        return $info;
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass();
        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;
        $breakDowns = $price['passengerFares'];
        $passengerCounts = $price['passengerCounts'];
        foreach ($breakDowns as $index => $priceDetails) {
            $passengerType = ['ADT' => 'adult', 'CHD' => 'child', 'INF' => 'infant'][$index];
            $typeCount = $passengerCounts[$index];
            if ($typeCount == 0) continue;
            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                lugError($passengerType . ' count mismatch', ['passengersTypeCount' => $this->passengersTypeCount, 'result count' => $price['passengerCounts']]);
            } else {
                ///> to check later that all the requested passengers types are available in th eresult
                ${$passengerType . 'Count'} = 'exists';
            }

            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['base'],
                'tax' => $priceDetails['taxes'],
                'totalFare' => $priceDetails['total'],
                'commission' => 0,
                'payable' => $priceDetails['total'],
                'passengerTypeTotal' => [
                    'baseFare' => $priceDetails['base'] * $typeCount,
                    'tax' => $priceDetails['taxes'],
                    'totalFare' => $priceDetails['total'] * $typeCount,
                    'commission' => 0,
                    'payable' => $priceDetails['total'] * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];
        }

        ///> checking if all the passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            // return false;
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $price['price']['base'];
        $newPrice->payable = $price['price']['total'];
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function legMapper($leg)
    {
        $newLeg = new stdClass();
        $newLeg->segments = $newLeg->airlines = $legFlightNumbers = $resBookDesigCodes = $capacities = [];
        $segments = $leg['segments'];
        $newLeg->stops = count($segments) - 1;
        foreach ($segments as $key => $segment) {
            $marketingAirline = $operatingAirline = $this->airlines[$segment['airline']['code']] ?? Null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['airline']['code'],
                    'descriptions' => $segment['airline']['name'],
                    'data_reference' => types('supplierDataReference', 'CloudFares')
                ];
                return false;
            }
            $cabin = $segment['class']['service'];
            if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) { ///> this way we'll include Premium options in the results
                // lugWarning(
                //     'cabin mismatch',
                //     ['request' => $this->queryParams['cabin'], 'result' => $cabin, 'segment' => $segment]
                // );
                return false;
            }

            $departureAirport = $this->airports[$segment['from']['code']] ?? '';
            if (empty($departureAirport)) { ///> means the Airport is not in database
                lugWarning('DepartureAirport not found ' . ($segment), []);
                return false;
            }
            $arrivalAirport = $this->airports[$segment['to']['code']] ?? '';
            if (empty($arrivalAirport)) { ///> means the Airport is not in database
                lugWarning('ArrivalAirport not found ' . ($segment), []);
                return false;
            }
            $departureCity = $this->airports[$segment['from']['code']]['city']['title'] ?? $segment['from']['code'];
            $arrivalCity = $this->airports[$segment['to']['code']]['city']['title'] ?? $segment['to']['code'];
            $departureDateTime = $this->humanDateTime($segment['departure']['formated_time']);
            $arrivalDateTime = $this->humanDateTime($segment['arrival']['formated_time']);
            $arivalTerminal = $departureTerminal = $segment['terminal'] ?? '-';

            $baggage = $this->baggageModifier($segment);
            $airplane = $this->airplanes[$segment['equipment']]['title'] ?? $segment['equipment'];
            //time
            $segmentTime = $segment['duration'] ?? $segment['flight_time'];
            $durations[] =  $duration = intdiv((int)$segmentTime, 60) . ':' . ((int)$segmentTime % 60);
            $durations[] = $between2Time = $this->timeDiffInMinnutes(
                $segment['arrival']['formated_time'],
                $segments[$key + 1]['departure']['formated_time'] ?? $segment['arrival']['formated_time']
            );
            $afterFlight = intdiv($between2Time, 60) . ':' . ($between2Time % 60);

            $legFlightNumbers[] = $flightNumber = $segment['flight_number'];
            $resBookDesigCodes[] = $resBookDesigCode = $segment['class']['booking'];
            $capacities[] = $capacity = ((int) $segment['class']['seats_available']) ?? null;
            $fareBasis = $segment['class']['fare_basis'];

            $newLeg->airlines[] = $marketingAirline['id'];
            $newLeg->segments[$key] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' => $segment['departure']['formated_time']
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' => $segment['arrival']['formated_time']
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
                'FareBasis' => $fareBasis
            ];
        }
        $legDuration = intdiv($leg['duration'], 60) . ':' . ($leg['duration'] % 60);
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
        preg_match_all('/"plating_carrier"\s*:\s*{\s*"code"\s*:\s*"(\w+)"/', $this->rawResult['body'], $platingCarrierCodes);
        preg_match_all('/"airline"\s*:\s*{\s*"code"\s*:\s*"(\w+)"/', $this->rawResult['body'], $airlineCodes);
        preg_match_all('/"from"\s*:\s*{\s*"code"\s*:\s*"(\w+)"/', $this->rawResult['body'], $departureAirportCodes);
        preg_match_all('/"to"\s*:\s*{\s*"code"\s*:\s*"(\w+)"/', $this->rawResult['body'], $arrivalAirportCodes);
        preg_match_all('/"equipment":\s*"([^"]*)"/', $this->rawResult['body'], $equipments);
        $this->airports = $this->loadAirports(array_unique(array_merge($departureAirportCodes[1], $arrivalAirportCodes[1])));
        $this->airlines = $this->loadAirLines(array_unique(array_merge($platingCarrierCodes[1], $airlineCodes[1])));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments[1]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['AirportCodes' => array_unique(array_merge($departureAirportCodes[1], $arrivalAirportCodes[1])), 'AirlineCode' => array_unique(array_merge($airlineCodes[1], $vAirlineCodes[1], $vAirlineCodes[1])), '$equipments' => $equipments, '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality', 'passport_expiry', 'passport_number']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['directions'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['plating_carrier']['code'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return ['passengerFares' => $itinerary['passengerFares'], 'price' => $itinerary['price'], 'passengerCounts' => $itinerary['passengerCounts'], 'commission' => $itinerary['commission']];
    }

    protected function timeDiffInMinnutes($start, $end)
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);
        return $end->diffInMinutes($start);
    }

    protected function prepareInfo($itinerary)
    {
        if (!empty($itinerary['baggage_allowance']) && is_array($itinerary['baggage_allowance'])) {
            foreach ($itinerary['baggage_allowance']  as $baggage_allowances) {
                if (!empty($baggage_allowances['baggage_allowance']) && is_array($baggage_allowances['baggage_allowance'])) {
                    foreach ($baggage_allowances['baggage_allowance'] as $baggage_allowance) {
                        $this->baggageInfo[$baggage_allowance['from']['code'] . '/' . $baggage_allowance['to']['code']][$baggage_allowance['traveler_type']] = $baggage_allowance['bag_details'];
                    }
                }
            }
        }
    }

    protected function baggageModifier($segment)
    {
        $text = '';
        if (!empty($this->baggageInfo[$segment['from']['code'] . '/' . $segment['to']['code']])) {
            foreach ($this->baggageInfo[$segment['from']['code'] . '/' . $segment['to']['code']] as $type => $bagDetails) {
                if (!empty($bagDetails['unit'])) {
                    $text = "$text {$bagDetails['amount']} {$bagDetails['unit']}/{$type} -";
                }
            }
        }
        return rtrim(ltrim($text), '-');
    }

    protected function searchUrl()
    {
        return '/api/b2b/v1/flight/low-fare-search';
    }

    protected function getHeader()
    {
        return  ['Authorization:Bearer ' . $this->latestSession];
    }

    protected function checkOpenReturnEmptyResult($openReturnResult)
    {
        return empty($openReturnResult['decoded']['data']['search_flight']);
    }

    protected function checkServiceInfoValidity($legs)
    {
        $or = true;
        if ($this->queryParams['tripType'] === 'openReturn') {
            $or = $this->OpenReturnCheckServiceInfoValidity($legs);
        }
        return $or && $this->mainCheckServiceInfoValidity($legs);
    }
}

<?php

namespace App\TravelServices\Suppliers\Local\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use MatinUtils\NodeTracker\Models\AirPort;
use MatinUtils\NodeTracker\Models\FlightPricing;
use stdClass;

trait Search
{
    use FlightSearch;

    protected $originAirports = [], $destinationAirports = [], $validatingAirline;

    protected function getContent()
    {
        $this->getAirports();

        ///> getting the result for the 1way search
        ///> for roundTrips capacity is important. for oneWay search we don't have a condition for remaining capacity here, it depends on the pricing class, we'll check later.
        foreach ($this->queryParams['legs'] as $legIndex => $leg) {
            $this->rawResult[$legIndex] = FlightPricing::when($this->isOneWaySearch, function ($pricingQuery) {
                $pricingQuery->where(function ($q) {
                    $q->whereNull('buyer_id')->orWhere('buyer_id', $this->queryParams['buyer_id'] ?? null);
                });
            })->when(!$this->isOneWaySearch, function ($pricingQuery) {
                $pricingQuery->where('remaining', '>=', $this->passengersCount)->whereNull('buyer_id');
            })->where('remaining', '>', 0)->whereHas('availability', function ($availabilityQuery) use ($leg, $legIndex) {
                $availabilityQuery->whereDate('departure', $leg['departure'])
                    ->where('cabin', types('flightCabin', lcfirst($this->queryParams['cabin'])))
                    ->whereHas('flight', function ($flightQuery) use ($legIndex) {
                        $flightQuery->where('status', 1)
                            ->where(function ($q) use ($legIndex) {
                                $q->where('seller_id', $this->seller->id)
                                    ->where(function ($q2) use ($legIndex) {
                                        $q2->whereIn('origin_airport_id', $this->originAirports[$legIndex])
                                            ->orWhereIn('destination_airport_id', $this->destinationAirports[$legIndex]);
                                    });
                            });
                    });
            })->with([
                'availability.airplane',
                'availability.operatingAirline',
                'availability.flight.marketingAirline',
                'availability.flight.originAirport.city.country',
                'availability.flight.destinationAirport.city.country',
            ])->get();
            if (count($this->rawResult[$legIndex]) < 1) {
                $this->addError('No Available flights for these conditions');
            }
        }
    }

    protected function extractor()
    {
        $this->items = $this->itinerary($this->recommendations());
        if (empty($this->items)) {
            $this->addError('No matching flights');
        }
    }

    protected function recommendations()
    {
        foreach ($this->rawResult as $legIndex => $legResults) {
            ///> first we add flights that are nonstop
            foreach ($legResults as $key => $item) {
                if (in_array($item->availability->flight->origin_airport_id, $this->originAirports[$legIndex]) && in_array($item->availability->flight->destination_airport_id, $this->destinationAirports[$legIndex])) {
                    $items[$legIndex][] = [$item->id];
                    // $legResults->forget($key);
                }
            }
            ///> now we have to see if we can create multi segment flights
            ///> only if it is allowed by admin
            if (nodeConfigs('multiSegmentLocalFlights') == 'true') {
                ///> for now we only can generate flights that have 2 segment
                $firstSeg = $legResults->filter(fn($item) => in_array($item->availability->flight->origin_airport_id, $this->originAirports[$legIndex]) && !in_array($item->availability->flight->destination_airport_id, $this->destinationAirports[$legIndex]));
                $secondSeg = $legResults->filter(fn($item) => in_array($item->availability->flight->destination_airport_id, $this->destinationAirports[$legIndex]) && !in_array($item->availability->flight->origin_airport_id, $this->originAirports[$legIndex]));

                foreach ($firstSeg ?? [] as $firstSegItem) {
                    foreach ($secondSeg ?? [] as $secondSegItem) {
                        if ($this->segmentsCanBeCombined($firstSegItem, $secondSegItem)) {
                            $items[$legIndex][] = ["$firstSegItem->id,$secondSegItem->id"];
                        }
                    }
                }
            }
        }
        if (count($items ?? []) != count($this->rawResult)) return [];
        $combinations = array_shift($items);
        foreach ($items ?? [] as $legResults) {
            $temp = [];
            foreach ($legResults as $item) {
                foreach ($combinations as $combination) {
                    $temp[] = array_merge($combination, $item);
                }
            }
            $combinations = $temp;
        }
        foreach ($combinations ?? [] as $comboId => $combination) {
            $recommendation = [];
            foreach ($combination as $legIndex => $priceingId) {
                foreach (explode(',', $priceingId) as $priceingI) { ///> it is important to find them one by one instead of whereIn so keep the segments' order
                    $recommendation[$legIndex][] = $this->rawResult[$legIndex]->find($priceingI);
                }
                $recommendation[$legIndex] = collect($recommendation[$legIndex]); ///> so we can use ->first()
                ///> we need to make sure both legs' flights have the same ageRange, we have already checked that all segments of each leg have the same ageRange, so we'll use the first seg in each leg to compare
                $thisLegAgeRange = $recommendation[$legIndex]->first()->availability->flight->passengers_age_range;
                $prevLegAgeRange = empty($recommendation[$legIndex - 1]) ? $thisLegAgeRange : $recommendation[$legIndex - 1]->first()->availability->flight->passengers_age_range; ///> only empty id first leg
                $ageRangeIsTheSame = $thisLegAgeRange == $prevLegAgeRange;
            }
            if ($ageRangeIsTheSame) {
                $recommendations[$comboId] = $recommendation;
            }
        }
        return $recommendations ?? [];
    }

    protected function prepareInfo($itineraryLegs)
    {
        if ($this->isOneWaySearch) {
            $this->setLocalPassengersTypeCount($itineraryLegs[0]);
        }
        $this->getAirline($itineraryLegs);
    }

    protected function getAirline($itineraryLegs)
    {
        $validatingAirline = $itineraryLegs[0]->first()->availability->flight->marketingAirline()
            ->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb', 'logo')->first()->toArray();

        $this->airlines[$validatingAirline['abb']] = $validatingAirline;
        $this->validatingAirline = $validatingAirline['abb'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $this->validatingAirline;
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary;
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary;
    }

    protected function sellerInfoMapper($result)
    {
        $pricings = $result[0];
        $info = new stdClass();
        $info->onholdable = true;
        $info->afterSearch = [];
        $info->beforeBook = [];
        $info->extra = [
            'dedicated' => !empty($pricings->whereNotNull('buyer_id')->first()),
            'ageRange' => $pricings->first()->availability->flight->passengers_age_range //> at this point we know age range for all flights are the same
        ];
        foreach ($result as $legPricings) {
            foreach ($legPricings as $segPricing) {
                $info->extra['availabilities'][] = $segPricing->availability->id;
            }
        }
        return $info;
    }

    protected function getPassengersForm($result)
    {
        foreach ($result as $legPricings) {
            $forms = $legPricings->pluck('availability')->flatten()->pluck('flight')->flatten()->pluck('passengers_form');
            $passengers_forms[] = $this->combinepassengerForms(...$forms);
        }
        return $this->combinepassengerForms(...$passengers_forms);
    }

    protected function getFareRules($result)
    {
        foreach ($result as $key => $legPricings) {
            foreach ($legPricings as $pricing) {
                $rules = array_merge($rules ?? [], $this->fareRuleModifier($pricing->availability->rules));
            }
        }
        return $rules ?? [];
    }

    protected function legMapper($pricings)
    {
        $cabin = $this->queryParams['cabin'];
        $newLeg = new stdClass();
        $legFlightNumbers = $resBookDesigCodes = $newLeg->airlines = [];
        $newLeg->stops = count($pricings) - 1;
        foreach ($pricings as $key => $pricing) {
            $departureCity = $pricing->availability->flight->originAirport->city->title;
            $departureDateTime = $this->humanDateTime($pricing->availability->departure);
            $departureAirport = $pricing->availability->flight->originAirport()
                ->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb', 'city_id')->with(['City' => function ($cities) {
                    $cities->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb', 'country_id')->with(['country' => function ($countries) {
                        $countries->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb');
                    }]);
                }])->first()->toArray();
            $departureTerminal = $pricing->availability->origin_terminal ?? '-';

            $arrivalCity = $pricing->availability->flight->destinationAirport->city->title;
            $arrivalDateTime = $this->humanDateTime($pricing->availability->arrival);
            $arrivalAirport = $pricing->availability->flight->destinationAirport()
                ->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb', 'city_id')->with(['City' => function ($cities) {
                    $cities->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb', 'country_id')->with(['country' => function ($countries) {
                        $countries->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb');
                    }]);
                }])->first()->toArray();
            $arrivalTerminal = $pricing->availability->destination_terminal ?? '-';

            $operatingAirline = $pricing->availability->operatingAirline()
                ->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb', 'logo')->first()->toArray();
            $operatingAirline['supplier_key'] = $operatingAirline['abb'];

            $marketingAirline = $pricing->availability->flight->marketingAirline()
                ->select('id', 'en', 'fa', 'ar', 'ku', 'tr', 'abb', 'logo')->first()->toArray();
            $marketingAirline['supplier_key'] = $marketingAirline['abb'];
            $baggages[] = $pricing->availability->baggage ?? 0;
            $baggage = ($pricing->availability->baggage ?? 0) . ' KG';
            $airplane = $pricing->availability->airplane->title ?? '-';
            $durations[] = $duration = $pricing->availability->duration;
            $durations[] =  $afterFlights[] = $afterFlight = $this->between2Time(
                $pricing->availability->arrival,
                $pricings[$key + 1]->availability->departure ?? $pricing->availability->arrival
            );
            $legFlightNumbers[] = $flightNumber = $pricing->availability->flight_number;
            $resBookDesigCodes[] = $resBookDesigCode = $pricing->class;
            $newLeg->airlines[] = $marketingAirline['id'];

            $newLeg->segments[] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' => $pricing->availability->departure,
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arrivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' => $pricing->availability->arrival,
                ],
                'airline' => $marketingAirline,
                'operatingAirline' => $operatingAirline,
                'cabin' => $cabin,
                'luggage' => $baggage,
                'airplane' => $airplane,
                'duration' => $duration,
                'ticket_type' => 'system', ///?????????????????????
                'flight_number' => $flightNumber,
                'after_flight_stop' => $afterFlight,
                'resBookDesigCode' => $resBookDesigCode,
                'capacity' => $pricing->remaining
            ];
        }
        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[count($newLeg->segments) - 1]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $cabin,
            'luggage' => min($baggages),
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' => $this->sumDurations($durations),
            'connections' => $newLeg->stops,
            'ticket_type' => 'system', ///?????????????????????
            'flight_number' => implode('/', $legFlightNumbers),
            'after_flight_stop' => $this->sumDurations($afterFlights),
            'resBookDesigCode' => implode('/', $resBookDesigCodes),
            'capacity' => $pricings->min('remaining')
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = implode('/', $resBookDesigCodes);
        return $newLeg;
    }

    protected function priceMapper($pricings)
    {
        $newPrice = new stdClass();

        $adultCount = $this->passengersTypeCount['adults'];
        $childCount = $this->passengersTypeCount['children'];
        $infantCount = $this->passengersTypeCount['infants'];

        foreach ($pricings as $legPricing) {
            foreach ($legPricing  as $segPricing) {
                $priceList['adult']  =  ($priceList['adult'] ?? 0)  + $segPricing['adult_price'];
                if ($childCount > 0) {
                    $priceList['child']  =  ($priceList['child'] ?? 0)  + $segPricing['child_price'];
                }
                if ($infantCount > 0) {
                    $priceList['infant']  =  ($priceList['infant'] ?? 0)  + $segPricing['infant_price'];
                }
            }
        }


        $totalPayable = 0;
        foreach ($priceList as $passengerType => $typePrice) {
            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $typePrice,
                'tax' => 0,
                'totalFare' => $typePrice,
                'commission' => 0,
                'payable' => $typePrice,
                'passengerTypeTotal' => [
                    'baseFare' => $typePrice * ${$passengerType . 'Count'},
                    'tax' => 0,
                    'totalFare' => $typePrice * ${$passengerType . 'Count'},
                    'commission' => 0,
                    'payable' => $typePrice * ${$passengerType . 'Count'},
                    'passengersCount' => ${$passengerType . 'Count'}
                ]
            ];
            $totalPayable += $newPriceBreakDowns[$passengerType]['passengerTypeTotal']['payable'];
        }
        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = node()->currency;
        $newPrice->baseFare = $totalPayable;
        $newPrice->payable = $totalPayable;
        $newPrice->guaranteed = !empty($pricings[0]->first()['buyer_id']); ///> quaranteed flights are only available in 1way flights and for 1way flights either all the segments are guaranteed or none
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function getAirports()
    {
        foreach ($this->queryParams['legs'] as $legIndex => $leg) {
            ///> get airports befor foreach
            if (empty($leg['origin']['airport'])) {
                $cityId = $leg['origin']['city']['id'];
                $this->originAirports[$legIndex] = AirPort::where('city_id', $cityId)->get()->pluck('id')->toArray();
            } else {
                $this->originAirports[$legIndex] = [$leg['origin']['airport']['id']];
            }

            if (empty($leg['destination']['airport'])) {
                $cityId = $leg['destination']['city']['id'];
                $this->destinationAirports[$legIndex] = AirPort::where('city_id', $cityId)->get()->pluck('id')->toArray();
            } else {
                $this->destinationAirports[$legIndex] = [$leg['destination']['airport']['id']];
            }
        }
    }

    protected function fareRuleModifier($rules)
    {
        ///> only company users and indivisuals who are under a company can view private rules
        static $userCanSeeAllRules;
        foreach ($rules[0]['details'] ?? [] as $index => $detail) {

            if (empty($detail['publicShow'])) {

                if (empty($userCanSeeAllRules)) {
                    ///> i put this here bwcause i wanted the query to only execute if there is a private rule
                    $user = findBuyer($this->queryParams['buyer_id'])?->user;
                    $userCanSeeAllRules = ((($user->is_indivisual ?? true) && empty($user->parent))) ? 'hide' : 'show';
                }

                if ($userCanSeeAllRules == 'hide') {
                    $unsetHappened = true;
                    unset($rules[0]['details'][$index]);
                }
            }
        }

        if ($unsetHappened ?? false) {
            $rules[0]['details'] = array_values($rules[0]['details']);
        }

        return $rules;
    }

    protected function setLocalPassengersTypeCount($firstLegPricings)
    {
        $adultCount = $this->queryParams['adults'];
        $childCount = $this->queryParams['children'] ?? 0;
        $infantCount = $this->queryParams['infants'] ?? 0;

        $remaining = $firstLegPricings->min("remaining");
        if ($remaining < $this->passengersCount) {
            $available = $remaining;
            if ($available >= $adultCount) {
                $available -= $adultCount;
                if ($available < $childCount) {
                    $childCount = $available;
                }
            } else {
                $adultCount = $available;
                $childCount = 0;
            }
            if ($adultCount < $infantCount) {
                $infantCount = $adultCount;
            }
        }

        $this->passengersTypeCount = [
            'adults' => $adultCount,
            'children' => $childCount,
            'infants' => $infantCount
        ];
    }

    protected function getQueryParams()
    {
        $queryParams = $this->queryParams;
        $queryParams['adults'] = $this->passengersTypeCount['adults'];
        $queryParams['children'] = $this->passengersTypeCount['children'];
        $queryParams['infants'] = $this->passengersTypeCount['infants'];
        return $queryParams;
    }

    protected function combinepassengerForms($pricing, $returnPricing = null)
    {
        $passengersForm = [
            'all' => ($returnPricing['all'] ?? [])  + $pricing['all'],
            'lead' => ($returnPricing['lead'] ?? []) + ($pricing['lead'] ?? [])
        ];
        if (empty($passengersForm['lead'])) {
            unset($passengersForm['lead']);
        }
        return $passengersForm;
    }

    protected function segmentsCanBeCombined($firstSegItem, $secondSegItem)
    {
        if ($firstSegItem->availability->flight->passengers_age_range != $secondSegItem->availability->flight->passengers_age_range) return false;
        if ($firstSegItem->availability->flight->marketing_airline_id != $secondSegItem->availability->flight->marketing_airline_id) return false;
        if ($firstSegItem->buyer_id != $secondSegItem->buyer_id) return false; ///> either all segments are guaranteed or none
        if ($firstSegItem->availability->arrival > $secondSegItem->availability->departure || $firstSegItem->availability->arrival->diffInHours($secondSegItem->availability->departure) < 1) return false;
        return true;
    }
}

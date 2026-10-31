<?php

namespace App\TravelServices\Suppliers\Local\Logics\Flight;

use App\TravelServices\Logics\Flight\TargetSearch as CommonTargetSearch;
use App\TravelServices\Logics\Flight\CustomSearch as CommonCustomSearch;
use MatinUtils\NodeTracker\Models\FlightPricing;

trait TargetSearch
{
    use CommonTargetSearch, Search, CommonCustomSearch {
        CommonCustomSearch::itinerary insteadof Search;
        CommonCustomSearch::done insteadof Search;
        CommonCustomSearch::failed insteadof Search;
        CommonTargetSearch::priceInfo insteadof CommonCustomSearch;
        CommonTargetSearch::chekCapacity insteadof CommonCustomSearch;
    }

    protected function getContent()
    {
        foreach ($this->queryParams['legs'] as $legIndex => $leg) {
            $this->originAirports[$legIndex][] =  $leg['origin']['airportId']; ///> this is for search methodes to use
            $this->destinationAirports[$legIndex][] = $leg['destination']['airportId']; ///> this is for search methodes to use
            $this->rawResult[$legIndex] = FlightPricing::when($this->isOneWaySearch, function ($pricingQuery) {
                $pricingQuery->where(function ($q) {
                    $q->whereNull('buyer_id')->orwhere('buyer_id', $this->queryParams['buyer_id'] ?? null);
                });
            })->when(!$this->isOneWaySearch, function ($pricingQuery) {
                $pricingQuery->where('remaining', '>=', $this->passengersCount)->whereNull('buyer_id');
            })->whereHas('availability', function ($availabilityQuery) use ($leg) {
                $availabilityQuery->whereDate('departure', $leg['departure'])
                    ->where('cabin', types('flightCabin', lcfirst($this->queryParams['cabin'])))->whereHas('flight', function ($flightQuery) use ($leg) {
                        $flightQuery->where('seller_id', $this->seller->id)->where('status', 1)
                            ->where('origin_airport_id',  $leg['origin']['airportId'])
                            ->orWhere('destination_airport_id',  $leg['destination']['airportId'])
                            ->whereExists(function ($marketingAirline) use ($leg) {
                                $marketingAirline->from(commonConnectionDBName()  . '.airlines')
                                    ->whereColumn('operating_airline_id', 'id')
                                    ->where('id', $leg['airlineId']);
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
}

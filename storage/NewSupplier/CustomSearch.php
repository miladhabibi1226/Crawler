<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\DummyTravelService;

use App\TravelServices\Logics\DummyTravelService\CustomSearch as CommonCustomSearch;

trait CustomSearch
{
    use Search, CommonCustomSearch {
        CommonCustomSearch::itinerary insteadof Search;
        CommonCustomSearch::done insteadof Search;
        CommonCustomSearch::failed insteadof Search;
    }

    public function canBeDone()
    {
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

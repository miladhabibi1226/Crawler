<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Flight;

use App\TravelServices\Logics\Flight\CustomSearch as CommonCustomSearch;
use MatinUtils\NodeTracker\Models\ServiceInfo;

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
    
    protected function retrieveServiceInfo($results) ///> rewritten in custom search
    {
        $this->serviceInfos = empty($results) ? [] : ServiceInfo::whereIn('reference_id', array_column($results, 'serviceInfo'))->whereType('serviceInfo')->pluck('data', 'reference_id')->toArray();
    }

    protected function originDestinationOption($itinerary)
    {
        return $this->serviceInfos[$itinerary['serviceInfo']];
    }

    protected function legModifier($rawLegs, $validatingAirline)
    {
        return $rawLegs;
    }

    protected function validatingAirlineCode($itinerary)
    {
        return [];
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['priceDetails'];
    }
}

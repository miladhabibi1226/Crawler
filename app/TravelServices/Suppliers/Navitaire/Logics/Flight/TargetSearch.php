<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics\Flight;

use App\TravelServices\Logics\Flight\TargetSearch as CommonTargetSearch;

trait TargetSearch
{
    use CommonTargetSearch, CustomSearch {
        CommonTargetSearch::priceInfo insteadof CustomSearch;
        CommonTargetSearch::chekCapacity insteadof CustomSearch;

    }
    public function canBedone()
    {
        $this->authorizeRequest();
        if (!$this->translatedQueryParams = $this->translateQueryParams(true)) {
            return $this->addError('Query params translation failed');
        }
    }
}

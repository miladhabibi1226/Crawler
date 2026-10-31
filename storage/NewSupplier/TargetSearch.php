<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\DummyTravelService;

use App\TravelServices\Logics\DummyTravelService\TargetSearch as CommonTargetSearch;

trait TargetSearch
{
    use CommonTargetSearch, CustomSearch{
        CommonTargetSearch::priceInfo insteadOf CustomSearch;
    }
}

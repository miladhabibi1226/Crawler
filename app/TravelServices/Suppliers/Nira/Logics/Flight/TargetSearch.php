<?php

namespace App\TravelServices\Suppliers\Nira\Logics\Flight;

use App\TravelServices\Logics\Flight\TargetSearch as CommonTargetSearch;

trait TargetSearch
{
    use CommonTargetSearch, CustomSearch{
        CommonTargetSearch::priceInfo insteadOf CustomSearch;
        CommonTargetSearch::chekCapacity insteadof CustomSearch;
    }
}

<?php

namespace App\TravelServices\Suppliers\Sepehr\Logics\Flight;

use App\TravelServices\Logics\Flight\TargetSearch as CommonTargetSearch;

trait TargetSearch
{
    use CommonTargetSearch, CustomSearch {
        CommonTargetSearch::priceInfo insteadof CustomSearch;
        CommonTargetSearch::chekCapacity insteadof CustomSearch;
    }
}

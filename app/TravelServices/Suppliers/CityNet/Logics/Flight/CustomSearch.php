<?php

namespace App\TravelServices\Suppliers\CityNet\Logics\Flight;

use App\TravelServices\Logics\Flight\CustomSearch as CommonCustomSearch;

trait CustomSearch
{
    use Search, CommonCustomSearch {
        CommonCustomSearch::itinerary insteadof Search;
        CommonCustomSearch::done insteadof Search;
        CommonCustomSearch::failed insteadof Search;
    }
}

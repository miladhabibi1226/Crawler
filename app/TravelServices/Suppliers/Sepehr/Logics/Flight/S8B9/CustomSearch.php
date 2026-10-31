<?php

namespace App\TravelServices\Suppliers\Sepehr\Logics\Flight\S8B9;

use App\TravelServices\Logics\Flight\CustomSearch as CommonCustomSearch;

trait CustomSearch
{
    use Search, CommonCustomSearch {
        CommonCustomSearch::done insteadof Search;
        CommonCustomSearch::failed insteadof Search;
        CommonCustomSearch::itinerary insteadof Search;
    }

    public function canBeDone()
    {
    }
}

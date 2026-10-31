<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight\New;

use App\TravelServices\Logics\Flight\CustomSearch as CommonCustomSearch;

trait CustomSearch
{
    use Search, CommonCustomSearch {
        CommonCustomSearch::itinerary insteadof Search;
        CommonCustomSearch::done insteadof Search;
        CommonCustomSearch::failed insteadof Search;
    }
}

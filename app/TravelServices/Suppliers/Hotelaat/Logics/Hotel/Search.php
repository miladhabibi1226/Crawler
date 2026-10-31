<?php

namespace App\TravelServices\Suppliers\Hotelaat\Logics\Hotel;

use App\TravelServices\Suppliers\IRIX\Logics\Hotel\Search as IRIXSearch;
use Illuminate\Support\Facades\DB;


trait Search
{

    use IRIXSearch ;

    protected function getsidingTableName()
    {
        return "Hotelaat_hotels_detail";
    }

}

<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight\Old;

use App\TravelServices\Suppliers\Accelaero\Logics\Flight\PriceQuote as commonPriceQuote;

trait PriceQuote
{
    use commonPriceQuote;
    protected function extraCheck($PricedItinerary)
    {
        return true;
    }
}

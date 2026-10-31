<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight\New;

use App\TravelServices\Suppliers\Accelaero\Logics\Flight\PriceQuote as commonPriceQuote;


trait PriceQuote
{
    use commonPriceQuote;
    protected function extraCheck($PricedItinerary)
    {
        return $this->compareSegments($PricedItinerary, $this->getServiceInfo());
    }
}

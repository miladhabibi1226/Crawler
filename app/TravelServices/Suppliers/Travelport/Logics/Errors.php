<?php

namespace App\TravelServices\Suppliers\Travelport\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            ///> not From spplier
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            'Session Expired' => ['group' => "expired", 'class' => 'technical'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Price has changed" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
        ];
    }
}

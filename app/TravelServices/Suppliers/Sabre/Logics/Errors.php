<?php

namespace App\TravelServices\Suppliers\Sabre\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            "ERR.SP.CLIENT.VALIDATION_FAILED" => ['group' => 'technical', 'class' => 'technical'],
            "ERR.SP.FLIGHT_NOT_FOUND" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],


            ///> not From spplier
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            'Session Expired' => ['group' => "expired", 'class' => 'technical'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Price has changed" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
        ];
    }
}

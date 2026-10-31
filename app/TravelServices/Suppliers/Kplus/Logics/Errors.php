<?php

namespace App\TravelServices\Suppliers\Kplus\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            "Availability not found" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Invalid key(s)" => ['group' => "unavailableOrPriceChange", 'class' => 'technical'],
            "Invalid token code" => ['group' => "unavailableOrPriceChange", 'class' => 'technical'],

            "Invalid country" => ['group' => 'technical', 'class' => 'technical'],
            "Balance is not enough" => ['group' => 'technical', 'class' => 'technical'],
            "Price has been changed" => ['group' => 'technical', 'class' => 'customer'],
            "Insufficient permission" => ['group' => 'technical', 'class' => 'technical'],
            "Invalid passport number" => ['group' => "technical", 'class' => 'customer'],

            ///> not From spplier
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "response is invalid: " => ['group' => 'supplier', 'class' => 'technical'],
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Price has changed" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
        ];
    }
}

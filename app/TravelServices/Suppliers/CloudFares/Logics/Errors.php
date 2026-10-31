<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            "Availability not found" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Invalid key(s)" => ['group' => "expired", 'class' => 'technical'],
            "Invalid token code" => ['group' => "expired", 'class' => 'technical'],

            "Invalid country" => ['group' => 'technical', 'class' => 'technical'],
            "Balance is not enough" => ['group' => 'technical', 'class' => 'technical'],
            "Price has been changed" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            "Insufficient permission" => ['group' => 'technical', 'class' => 'technical'],
            "Invalid passport number" => ['group' => "technical", 'class' => 'customer'],

            ///> not From spplier
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "response is invalid: " => ['group' => 'supplier', 'class' => 'technical'],
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Price has changed" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Tecknical error happened" => ['group' => 'supplier', 'class' => 'customer'],
        ];
    }
}

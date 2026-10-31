<?php

namespace App\TravelServices\Suppliers\Avtra\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            "Price(s) cannot be validated" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            "No Availability on requested date" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "No Flights or No Availability on date requested" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],

            "Invalid telephone number - Country code does not exists" => ['group' => "technical", 'class' => 'customer'],
            "Payment rejected-Agent Credit not enough" => ['group' => "technical", 'class' => 'technical'],
            "Invalid Country Code" => ['group' => "technical", 'class' => 'customer'],
            "Origin/Destination invalid" => ['group' => "technical", 'class' => 'technical'],
            "Invalid telephone number" => ['group' => "technical", 'class' => 'customer'],
            "Error Occurred- please report" => ['group' => "technical", 'class' => 'customer'],
            "Invalid passenger nationality" => ['group' => "technical", 'class' => 'customer'],
            "Invalid passport number" => ['group' => "technical", 'class' => 'customer'],

            ///> not From spplier
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "response is invalid: " => ['group' => 'supplier', 'class' => 'technical'],
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
        ];
    }
}

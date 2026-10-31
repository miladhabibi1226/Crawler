<?php

namespace App\TravelServices\Suppliers\GRS\Logics;

trait Errors
{

    protected function errorPatterns()
    {
        return [
            "booker_phone: فرمت booker phone معتبر نیست." => ['group' => 'supplier', 'class' => 'technical'],

            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "response is invalid: " => ['group' => 'supplier', 'class' => 'technical'],
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Price has changed" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
        ];
    }
}

<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            "Change flight price or date time" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            "The flight manifest is closed and it is not possible to provide any service" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            "Flight not found" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            "Flight capacity full" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Capacity Full (s)" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "FLIGHT STATUS CHANGED OR FAIL" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],


            "PhoneNumber is incorrect" => ['group' => "technical", 'class' => 'technical'], ///> putting this in node group bc the phone number that is being sent to supplier comes from seller configs
            "Reseller Credit is not Enough" => ['group' => 'technical', 'class' => 'technical'],
            "GivenName is mandatory" => ['group' => 'technical', 'class' => 'technical'],
            "The number of passengers in a PNR is not more than 9 people" => ['group' => 'technical', 'class' => 'technical'],
            "Passport ID Is Incorrect" => ['group' => "technical", 'class' => 'customer'],
            "BirthDate can not be less than 10 days" => ['group' => "technical", 'class' => 'customer'], ///> when i was fixing infats birthDate validity , i put infants BirthDate less than 10 days and i got this error (this error happened in booking)
            "Repetitive passenger in this flight" => ['group' => "technical", 'class' => 'customer'], ///> when national id is Repetitive
            "Passport Expire Date Is Incorrect" => ['group' => "technical", 'class' => 'customer'],

            "Unexpected error, try again" => ['group' => 'supplier', 'class' => 'technical'],

            ///> not From spplier
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "response is invalid" => ['group' => 'supplier', 'class' => 'technical'],
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Price has changed" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
        ];
    }
}

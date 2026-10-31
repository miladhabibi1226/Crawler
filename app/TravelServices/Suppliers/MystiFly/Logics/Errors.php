<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            "Invalid Session" => ['group' => 'expired', 'class' => 'technical'],

            "Itinerary Price has been revised - Kindly Re - Search for updated price." => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            "Invalid MFRef." => ['group' => 'unavailableOrPriceChange', 'class' => 'technical'],
            "Inventory Lost - Request an alternate flight" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            "Unable to confirm - Request an alternate flight" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],

            "Pending Need - Awaiting carrier response - Booking Unconfirmed" => ['group' => 'technical', 'class' => 'technical'],
            "Validation Error" => ['group' => 'technical', 'class' => 'technical'],
            "Booking already exists!" => ['group' => 'technical', 'class' => 'customer'],
            "At least one adult age must be 18 years or more as on date of travel." => ['group' => 'technical', 'class' => 'technical'],
            "Booking is already in Progress." => ['group' => 'technical', 'class' => 'customer'],
            "Date of birth is mandatory for Child and Infant." => ['group' => 'technical', 'class' => 'technical'],
            "You do not have sufficient balance in your account to order the ticket." => ['group' => 'technical', 'class' => 'technical'],

            "Timeout performing" => ['group' => 'supplier', 'class' => 'technical'],
            "Oops,Something went wrong!!" => ['group' => 'supplier', 'class' => 'technical'],

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

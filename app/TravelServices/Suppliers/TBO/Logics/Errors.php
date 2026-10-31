<?php

namespace App\TravelServices\Suppliers\TBO\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            ///> node
            "SystemErr: Technical Failure" =>  ['group' => 'supplier', 'class' => 'node'],
            "ProcessingErr: Technical Failure" =>  ['group' => 'supplier', 'class' => 'node'],
            "ProcessingErr: Booking Request price" =>  ['group' => 'supplier', 'class' => 'node'],
            "ValidationErr: Guest No 1: Title should not be null or empty or it should be one of the supported Titles ( Mr, Ms, Mrs, Miss )." =>  ['group' => 'supplier', 'class' => 'node'],

            ///> customer
            "ProcessingErr: The itinerary is not available, please check pricing response" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "ValidationErr: GuestNationality should be same as provided in Search Request" => ['group' => 'supplier', 'class' => 'technical'],

            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "response is invalid" => ['group' => 'supplier', 'class' => 'technical'],
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Price has changed" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
        ];
    }
}

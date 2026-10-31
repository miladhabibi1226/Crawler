<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            "Transaction invalid or expired" => ['group' => "expired", 'class' => 'customer'],

            "No availability" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Selected Baggages are not available at the moment" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Selected Flight Seats or Fares Not Available" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],

            "Insufficient agent available credit." => ['group' => 'technical', 'class' => 'technical'],
            "Airewards ID Validation Failed" => ['group' => 'technical', 'class' => 'technical'],
            "Authorization error" => ['group' => 'technical', 'class' => 'technical'],
            "City in address is required" => ['group' => 'technical', 'class' => 'technical'],
            "Invalid value" => ['group' => "technical", 'class' => 'technical'],
            "Price quote and booking segments are not matching" => ['group' => "technical", 'class' => 'technical'],

            "General failure occurred" => ['group' => "supplier", 'class' => 'technical'],

            ///> not From spplier
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "invalid response status:" => ['group' => "supplier", 'class' => 'technical'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
        ];
    }
}

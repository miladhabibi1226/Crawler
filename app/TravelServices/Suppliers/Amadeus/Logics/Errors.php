<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            "FARE ELEMENT ALREADY EXISTS FOR PASSENGER/SEGMENT" => ['group' => 'technical', 'class' => 'technical'],
            "MANDATORY SSRFOID MISSING FOR CARRIER" => ['group' => 'technical', 'class' => 'technical'],
            "55|Application|IGNORE AND RE-ENTER" => ['group' => 'technical', 'class' => 'technical'],
            "TICKETING INHIBITED-SSR DOCS MISSING" => ['group' => 'technical', 'class' => 'technical'],
            "NO FARES/RBD/CARRIER/PASSENGER TYPE" => ['group' => 'technical', 'class' => 'technical'],
            "TK ALREADY EXISTS FOR PASSENGER/SEGMENT" => ['group' => 'technical', 'class' => 'technical'],
            "101|Application|Unknown error" => ['group' => 'technical', 'class' => 'technical'],
            "93|Session|Illogical conversation" => ['group' => 'technical', 'class' => 'technical'],
            "CHECK SEGMENT CONTINUITY " => ['group' => 'technical', 'class' => 'technical'],
            "INFANT AGE MAY EXCEED FOR SOME SEGMENTS" => ['group' => 'technical', 'class' => 'technical'],
            "REQUIRES TICKET ON OR BEFORE" => ['group' => 'technical', 'class' => 'technical'],
            "UNABLE TO PROCESS" => ['group' => 'technical', 'class' => 'technical'],
            "NEED COMMISSION" => ['group' => 'technical', 'class' => 'technical'],
            "INVALID VALIDATING CARRIER OPTION USED WITH AIRLINE STOCK" => ['group' => 'technical', 'class' => 'technical'],
            "ENTRY NOT AUTHORISED" => ['group' => 'technical', 'class' => 'technical'],
            "ETKT DISALLOWED - NEED AIRLINE R/LOC-RETRY" => ['group' => 'technical', 'class' => 'technical'],
            "SIMULTANEOUS CHANGES TO PNR - USE WRA/RT TO PRINT OR IGNORE" => ['group' => 'technical', 'class' => 'technical'],
            "CT RJT: AQM: NO ENOUGH QUOTA" => ['group' => 'technical', 'class' => 'technical'],

            ///> not From spplier
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "response is invalid: " => ['group' => 'supplier', 'class' => 'technical'],
            "Undetected error" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Price has changed" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
        ];
    }
}

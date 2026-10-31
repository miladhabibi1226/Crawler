<?php

namespace App\TravelServices\Suppliers\Parto\Logics;

trait Errors
{

    protected function errorPatterns()
    {
        return [
            "SessionID cannot be null" => ['group' => 'expired', 'class' => 'technical'],
            "The payment deadline has expired for the given booking" => ['group' => 'expired', 'class' => 'technical'],
            "The ticketing time limit has expired for the given booking" => ['group' => 'expired', 'class' => 'technical'],
            "This result is not valid anymore,Please search again." => ['group' => "expired", 'class' => 'customer'],
            "FareSourceCode cannot be null" => ['group' => "expired", 'class' => 'technical'],

            "Sorry, There is a change in price" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            "We're sorry, the fare for that itinerary is no longer available." => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Price changed, Please contact us" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Flight class is restricted for foreign national travelers" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Selected itinerary is no longer available. Please select different option." => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],

            "An unexpected error occurred while processing. team Support notified!" => ['group' => 'technical', 'class' => 'customer'],
            "An unexpected error occurred while processing the AirBook call. API Support notified!" => ['group' => 'technical', 'class' => 'customer'],
            "An unexpected error occurred while processing the AirRevalidate call. API Support notified!" => ['group' => 'technical', 'class' => 'customer'],
            "You do not have sufficient balance in your account to order the booking" => ['group' => 'technical', 'class' => 'technical'],
            "is not a valid email address" => ['group' => 'technical', 'class' => 'technical'],
            "Booking failed by supplier" => ['group' => 'technical', 'class' => 'customer'],
            "Booking is currently unavailable. Please contact the site administrator." => ['group' => 'technical', 'class' => 'customer'],
            "Passenger's passport number is not valid." => ['group' => "technical", 'class' => 'customer'],
            "Booking may be pending or cancelled" => ['group' => "technical", 'class' => 'customer'],
            "Mobile Number is not valid" => ['group' => "technical", 'class' => 'technical'],
            "Sorry, There is a change in price" => ['group' => "technical", 'class' => 'customer'],
            "Invalid traveller nationality" => ['group' => "technical", 'class' => 'technical'],
            "National ID is not valid" => ['group' => "technical", 'class' => 'customer'],
            "Email is not valid" => ['group' => "technical", 'class' => 'technical'],
            "Destination address is mandatory" => ['group' => "technical", 'class' => 'technical'],
            "The minimum age of the Inafnt should be more than 15 days" => ['group' => "technical", 'class' => 'customer'],
            "Invalid National Code" => ['group' => "technical", 'class' => 'technical'],
            "Please check your Passport details" => ['group' => "technical", 'class' => 'customer'],
            "PassengerName cannot be null" => ['group' => "technical", 'class' => 'technical'],
            "There is duplicate booking for" => ['group' => "technical", 'class' => 'customer'],
            "Duplicate Booking, we are unable to complete your booking" => ['group' => "technical", 'class' => 'customer'],
            "passenger nationality is not supported" => ['group' => "technical", 'class' => 'technical'],
            "There is duplicate Passport Number" => ['group' => "technical", 'class' => 'customer'],

            "Temporary supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "Error Occurred- please report" => ['group' => 'supplier', 'class' => 'customer'],

            ////> NOT supplier error
            "Price has changed" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "invalid response status:" => ['group' => 'supplier', 'class' => 'technical'],
            "response is invalid: " => ['group' => 'supplier', 'class' => 'technical'],
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
        ];
    }

    protected function urgentInvestigationEerrorPatterns()
    {
        return [
            "There is duplicate booking for",
            "Duplicate Booking, we are unable to complete your booking",
            "Booking may be pending or cancelled",
            "An unexpected error occurred while processing. team Support notified!",
            "An unexpected error occurred while processing the AirBook call. API Support notified!",
            "Booking is currently unavailable. Please contact the site administrator.",
            "Temporary supplier connection error",
            "Error Occurred- please report",
        ];
    }
}

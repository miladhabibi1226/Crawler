<?php

namespace App\TravelServices\Suppliers\CityNet\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            "book time has expired" => ['group' => 'expired', 'class' => 'customer'],
            "End search validity or Already ticketed" => ['group' => 'expired', 'class' => 'customer'],
            "End search validity" => ['group' => 'expired', 'class' => 'customer'],
            "The link has already expired or is in  a contract" => ['group' => 'expired', 'class' => 'customer'],
            "Transaction invalid or expired" => ['group' => 'expired', 'class' => 'customer'],
            "Invalid FareSourceCode" => ['group' => 'expired', 'class' => 'technical'],

            "مغایرت مبلغ" => ['group' => 'unavailableOrPriceChange', 'class' => 'technical'],
            "Flight not found." => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            "Reservation Failure-Reservation Failed: 005 - NO SEAT(S) AVAILABLE" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Reservation Failure-FLIGHT CLASS IS RESTRICTED FOR FOREIGN NATIONAL TRAVELLERS" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Fare '(.*?)' not found on flight" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Selected itinerary is no longer available. Please select different option" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Capacity Full" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "ظرفیت کافی نمی باشد" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "تعداد صندلي درخواستي در پرواز رفت موجود نمي باشد" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "FLIGHT CLASS IS RESTRICTED FOR FOREIGN NATIONAL TRAVELLERS" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],

            "Failed to authenticate token" => ['group' => "technical", 'class' => 'technical'],
            "unknown error" => ['group' => "technical", 'class' => 'customer'],
            "Reseller Credit is not Enough!" => ['group' => "technical", 'class' => 'technical'],
            "There is not enough credit." => ['group' => 'technical', 'class' => 'technical'],
            "record Not found or  Already ticketed" => ['group' => 'technical', 'class' => 'technical'],
            "کد ملی تکراری" => ['group' => 'technical', 'class' => 'customer'],
            "عملیات با خطا مواجه شد" => ['group' => 'technical', 'class' => 'customer'],
            "INVALID DOCS NUMBER" => ['group' => 'technical', 'class' => 'customer'],
            "Repetitive passenger in this flight confirm" => ['group' => 'technical', 'class' => 'customer'],
            "length must be less than or equal to 10 characters long" => ['group' => "technical", 'class' => 'technical'],
            "failed reservation" => ['group' => "technical", 'class' => 'customer'],
            "ValidationError" => ['group' => "technical", 'class' => 'technical'],
            "لطفا با پشتيبانی فني تماس حاصل نمایید" => ['group' => "technical", 'class' => 'technical'],
            "passenger nationality is not supported" => ['group' => "technical", 'class' => 'customer'],
            "Reservation Failure-INVALID DOCS NUMBER." => ['group' => "technical", 'class' => 'customer'],
            "this service has been ticketed before" => ['group' => "technical", 'class' => 'customer'],
            "مسافر تکراری" => ['group' => "technical", 'class' => 'customer'],

            ///> not From spplier
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "response is invalid: " => ['group' => 'supplier', 'class' => 'technical'],
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
        ];
    }
}

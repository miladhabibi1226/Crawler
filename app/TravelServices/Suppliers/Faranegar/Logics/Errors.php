<?php

namespace App\TravelServices\Suppliers\Faranegar\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            "پرواز بسته شده است" => ['group' => 'unavailableOrPriceChange', 'class' => 'customer'],
            "ظرفیت کافی نمی باشد" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "مغایرت مبلغ" => ['group' => 'unavailableOrPriceChange', 'class' => 'technical'],

            "Internal error occurred contact support." => ['group' => 'technical', 'class' => 'technical'],
            "Authorization Failed" => ['group' => "technical", 'class' => 'technical'],
            "عدم اعتبار کافی" => ['group' => "technical", 'class' => 'technical'],
            "تعداد مسافر نوزاد در این پرواز بیشتر از حد مجاز است" => ['group' => 'technical', 'class' => 'customer'],
            "عملیات با خطا مواجه شد" => ['group' => 'technical', 'class' => 'technical'],
            "محدودیت سازمانی" => ['group' => 'technical', 'class' => 'technical'],
            "Mobile Can not be null" => ['group' => "technical", 'class' => 'technical'],
            "مسافر تکراری" => ['group' => "technical", 'class' => 'customer'],
            "قبلا بلیت صادر شده است" => ['group' => "technical", 'class' => 'customer'],
            "Exception in CreatePaxDetails: DocID is not valid" => ['group' => "technical", 'class' => 'customer'],
            "کد ملی تکراری" => ['group' => "technical", 'class' => 'customer'],

            ///> not From spplier
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            "response is invalid: " => ['group' => 'supplier', 'class' => 'technical'],
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
        ];
    }
}

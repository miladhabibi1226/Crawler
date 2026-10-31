<?php

namespace App\TravelServices\Suppliers\Sepehr\Logics;

trait Errors
{
    protected function errorPatterns()
    {
        return [
            "Fare '(.*?)' not found on flight" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "Flight not found. Number:" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            "The item is no longer available, or the price has changed" => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],

            "راي انجام اين رزرو کافي  نيست" => ['group' => "technical", 'class' => 'technical'],
            "پشتیبانی سایت تماس حاصل نمایید" => ['group' => "technical", 'class' => 'technical'],
            "یش از یکبار در رزرو تکرار شده است" => ['group' => 'technical', 'class' => 'customer'],
            "ساعت پرواز درخواستی شما" => ['group' => 'technical', 'class' => 'customer'],
            "در مسیر داخلی استفاده از گذرنامه برای اتباع ایرانی مجاز نمی باشد. لطفا اطلاعات کارت ملی را وارد نمایید." => ['group' => 'technical', 'class' => 'technical'],
            " بعد از بررسی مجدد تاریخ تولد و گروه سنی مسافر، مجددا از ابتدا رزرو نمایی" => ['group' => 'technical', 'class' => 'customer'],
            "is not valid format for passport number" => ['group' => 'technical', 'class' => 'customer'],
            "رزرو شده است" => ['group' => 'technical', 'class' => 'technical'],
            "مطابقت ندارد. لطفا رزرو خود را از ابتدا انجام دهید" => ['group' => 'technical', 'class' => 'technical'],
            "is not valid for Iranian Code Melli" => ['group' => "technical", 'class' => 'customer'],
            "is not a valid email addres" => ['group' => "technical", 'class' => 'technical'],
            "غیر معتبر است" => ['group' => "technical", 'class' => 'technical'],
            "در مسیر داخلی استفاده از گذرنامه برای اتباع ایرانی مجاز نمی باشد. لطفا اطلاعات کارت ملی را وارد نمایید." => ['group' => "technical", 'class' => 'customer'],
            "تعداد صندلي درخواستي در پرواز رفت موجود نمي باشد" => ['group' => "technical", 'class' => 'customer'],
            "شماره گذرنامه" => ['group' => "technical", 'class' => 'customer'],
            "کارت ملی" => ['group' => "technical", 'class' => 'customer'],

            "متاسفانه سیستم قادر به ادامه رزرو شما نیست. ممکن است این یه مشکل موقت باشد و بعد از چند دقیقه رفع شود" => ['group' => "supplier", 'class' => 'technical'],

            ///> not From spplier
            "Supplier connection error" => ['group' => 'supplier', 'class' => 'customer'],
            'Session Expired' => ['group' => "expired", 'class' => 'customer'],
            'This item is no longer available. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
            'The price has changed. Please search again' => ['group' => "unavailableOrPriceChange", 'class' => 'customer'],
        ];
    }
}

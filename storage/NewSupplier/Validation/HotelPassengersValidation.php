<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\Hotel;

use App\TravelServices\Logics\Hotel\PassengersValidation as HotelPassengersValidation;
trait PassengersValidation
{
    use HotelPassengersValidation;

    protected function allPassengersValidation($guest)
    {
        $this->validateRequired($guest, 'First_name');
        $this->validateRequired($guest, 'Last_name');
        $this->validateAge($guest, 'Age', 18);
    }
}

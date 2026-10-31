<?php

namespace App\TravelServices\Suppliers\IRIX\Logics\Hotel;

use App\TravelServices\Logics\Hotel\PassengersValidation as HotelPassengersValidation;

trait PassengersValidation
{
    use HotelPassengersValidation;

    protected function allPassengersValidation($guest)
    {
        $this->validateGender($guest, 'Gender');
        $this->validateRequired($guest, 'First_name');
        $this->validateRequired($guest, 'Last_name');
        $this->validateBirthdateByDeparture($guest, 'Date of birth', 2, 12);
    }

    protected function leadPassengerValidation($passenger, $key)
    {
        $this->validatePhone($passenger, 'Phone');
        $this->validateEmail($passenger, 'Email');
    }
}

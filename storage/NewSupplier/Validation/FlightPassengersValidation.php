<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\Flight;

use App\TravelServices\Logics\Flight\PassengersValidation as FlightPassengersValidation;

trait PassengersValidation
{
    use FlightPassengersValidation;

    protected function allPassengersValidation($passenger)
    {
        $this->validateGender($passenger, 'Gender');
        $this->validateLatinNames($passenger, 'First_name');
        $this->validateLatinNames($passenger, 'Last_name');
        $this->validateBirthdateByDeparture($passenger, 'Date of birth', 2, 12);
        $this->validateCountry($passenger, 'Nationality');
        $this->validateRequired($passenger, 'Passport Number');
        $this->validatePassportExpiry($passenger, 'Passport Expiry Date');
        $this->validateCountry($passenger, 'Passport Issuing Country');
    }

    protected function leadPassengerValidation($passenger, $key)
    {
        $this->validatePhone($passenger, 'Phone');
        $this->validateEmail($passenger, 'Email');
    }
}

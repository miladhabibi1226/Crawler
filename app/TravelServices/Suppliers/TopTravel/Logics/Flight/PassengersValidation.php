<?php

namespace App\TravelServices\Suppliers\TopTravel\Logics\Flight;

use App\TravelServices\Logics\Flight\PassengersValidation as CommonFlightPassengersValidation;
use Carbon\Carbon;

trait PassengersValidation
{
    use CommonFlightPassengersValidation;

    protected function allPassengersValidation($passenger)
    {
        $this->validateGender($passenger, 'Gender');
        $this->validateLatinNames($passenger, 'First_name');
        $this->validateLatinNames($passenger, 'Last_name');
        $this->validateBirthdateByDeparture($passenger, 'Date of birth', 2, 12);
        $this->validateCountry($passenger, 'Nationality');
        $this->validatePassportExpiry($passenger, 'Passport Expiry Date');
        $this->validatePassportNumber($passenger, 'Passport Number');
    }

    protected function leadPassengerValidation($passenger)
    {
        $this->validatePhone($passenger, 'Phone');
        $this->validateEmail($passenger, 'Email');
    }

    protected function supplierNameTitle($gender)
    {
        return strtoupper($gender) == 'MALE' ? 'MR' : 'MS';
    }
}

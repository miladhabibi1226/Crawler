<?php

namespace App\TravelServices\Suppliers\Kplus\Logics\Flight;

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
        $country = $this->validateCountry($passenger, 'Nationality');
        if (($this->object->seller_info['isTurkeyDomesticFlight'] ?? false) && ($country['abb'] ?? null) == 'TR') {
            $this->validateRequired($passenger, 'National Id');
        } else {
            $this->validatePassportExpiry($passenger, 'Passport Expiry Date');
            $this->validatePassportNumber($passenger, 'Passport Number');
        }
    }

    protected function leadPassengerValidation($passenger, $key)
    {
        $this->validatePhone($passenger, 'Phone');
        $this->validateEmail($passenger, 'Email');
    }
}

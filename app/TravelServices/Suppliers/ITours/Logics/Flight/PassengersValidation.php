<?php

namespace App\TravelServices\Suppliers\ITours\Logics\Flight;

use App\TravelServices\Logics\Flight\PassengersValidation as CommonFlightPassengersValidation;

trait PassengersValidation
{
    use CommonFlightPassengersValidation;

    protected function allPassengersValidation($passenger)
    {
        $this->validateGender($passenger, 'Gender');
        $this->validateLatinNames($passenger, 'First_name');
        $this->validateLatinNames($passenger, 'Last_name');
        $country = $this->validateCountry($passenger, 'Nationality');
        $this->validateBirthdateByDeparture($passenger, 'Date of birth', 2, 12);

        if ($this->object->seller_info['isIranianDomesticFlight'] ?? false) {
            $this->validatFarsiNames($passenger, 'Domestic first name');
            $this->validatFarsiNames($passenger, 'Domestic last name');
            $this->validateCodeMelli($passenger, 'National Id');
        } else {
            $this->validateRequired($passenger, 'Passport Number');
            $this->validatePassportExpiry($passenger, 'Passport Expiry Date');
            $this->validatePassportNumber($passenger, 'Passport Number');
        }
    }
}
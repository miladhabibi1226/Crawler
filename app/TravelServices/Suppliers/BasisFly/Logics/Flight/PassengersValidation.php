<?php

namespace App\TravelServices\Suppliers\BasisFly\Logics\Flight;

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

        $country = $this->validateCountry($passenger, 'Nationality');

        if (($country['abb'] ?? null) ==  'IR' && $this->object->seller_info['isDomesticFlight']) {
            $this->validateCodeMelli($passenger, 'National Id');
        } else {
            $this->validateCodeMelliNotRequired($passenger, 'National Id');
        }

        if (!($this->object->seller_info['isDomesticFlight'] ?? false) || ($country['abb'] ?? null) != 'IR') {
            $this->validatePassportNumber($passenger, 'Passport Number');
            $this->validatePassportExpiry($passenger, 'Passport Expiry Date');
        }
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

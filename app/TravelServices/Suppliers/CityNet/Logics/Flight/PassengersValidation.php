<?php

namespace App\TravelServices\Suppliers\CityNet\Logics\Flight;

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
        if (($country['abb'] ?? null) == 'IR') {
            $this->validateCodeMelli($passenger, 'National Id');
        }
        if (!($this->object->seller_info['isIranianDomesticFlight'] ?? false) || ($country['abb'] ?? null) != 'IR') {
            $this->validatePassportNumber($passenger, 'Passport Number');
            $this->validatePassportExpiry($passenger, 'Passport Expiry Date');
            $this->validateCountry($passenger, 'Passport Issuing Country');
        }
    }

    protected function leadPassengerValidation($passenger, $key)
    {
        $this->validatePhone($passenger, 'Phone');
        $this->validateEmail($passenger, 'Email');
    }

    protected function validatePassportNumber($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                if (strlen($passenger[$field]) < 6) {
                    $this->addError($this->transMessage('invalidLength', [$field]), $this->messageKey($field));
                }
            }
        }
    }
}

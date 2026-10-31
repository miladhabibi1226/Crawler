<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Flight;

use App\TravelServices\Logics\Flight\PassengersValidation as CommonFlightPassengersValidation;

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

        if ($this->object->seller_info['isDomesticFlight'] && ($country['abb'] ?? null) == 'IR') {
            $this->validateCodeMelli($passenger, 'National Id');
        } else {
            $this->validatePassportNumber($passenger, 'Passport Number');
            $this->validatePassportExpiry($passenger, 'Passport Expiry Date');
            $this->validateCountry($passenger, 'Passport Issuing Country');
        }
    }

    protected function leadPassengerValidation($passenger, $key)
    {
        $this->validatePhone($passenger, 'Phone');
    }

    protected function validateEmail($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                if (!filter_var($passenger[$field], FILTER_VALIDATE_EMAIL)) {
                    $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                } else {
                    $emailUser = explode('@', $passenger[$field])[0];
                    if (strlen($emailUser) <= 3) {
                        $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                    }
                }
            }
        }
    }
}

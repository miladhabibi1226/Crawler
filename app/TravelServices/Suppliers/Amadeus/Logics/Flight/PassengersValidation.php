<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

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
        $this->validateCountry($passenger, 'Passport Issuing Country');
        $this->validatePassportNumber($passenger, 'Passport Number', $this->validateCountry($passenger, 'Passport Issuing Country'));
        $this->validateFullNameLenghth($passenger);
    }

    protected function leadPassengerValidation($passenger)
    {
        $this->validateEmail($passenger, 'Email');
    }

    protected function validateFullNameLenghth($passenger)
    {
        $fullname = ($passenger['title'] ?? "") . ($passenger['First_name'] ?? "") . ($passenger['Last_name'] ?? "");
        if ($this->passengerData['type'] == 'children') {
            if (strlen($fullname) > 45) {
                $this->addError($this->transMessage('passengerFullNameLength'), $this->messageKey());
            }
        }
        if ($this->passengerData['type'] == 'adults') {
            if (strlen($fullname) > 58) {
                $this->addError($this->transMessage('passengerFullNameLength'), $this->messageKey());
            }
        }
        if ($this->passengerData['type'] == 'infants') {
            $fullname = ($passenger['title'] ?? "") . ($passenger['First_name'] ?? "");
            $adultNo1 = $passengersInfo['adults'][1] ?? [];
            $adultName = ($adultNo1['title'] ?? "") . ($adultNo1['First_name'] ?? "") . ($adultNo1['Last_name'] ?? "");
            if ((strlen($fullname) + strlen($adultName)) > 58) {
                $this->addError($this->transMessage('passengerFullNameLength'), $this->messageKey());
            }
        }
    }
}

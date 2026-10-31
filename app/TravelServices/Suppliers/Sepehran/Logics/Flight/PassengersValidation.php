<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics\Flight;

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
        return strtoupper($gender) == 'MALE' ? 'Mr' : 'Mrs';
    }

    protected function validateBirthdateByDeparture($passenger, $field, $minChild, $maxChild)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                preg_match("/\d{4}+-+\d{2}+-+\d{2}/", $passenger[$field] ?? null, $bd);
                if (empty($bd[0])) {
                    $this->addError($this->transMessage('invalidDateFormat', [$field]), $this->messageKey($field));
                } else {
                    $this->getLastDateOfTrip();
                    $birthdate = Carbon::parse($passenger[$field] ?? null);
                    if ($this->passengerData['type'] == 'adults') {
                        if ($this->departure->diffInYears($birthdate) < $maxChild || $this->departure->diffInYears($birthdate) >= 120) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $maxChild, 120]), $this->messageKey($field));
                        }
                    }
                    if ($this->passengerData['type'] == 'children') {
                        if ($this->departure->diffInYears($birthdate) < $minChild || $this->departure->diffInYears($birthdate) >= $maxChild) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minChild, $maxChild]), $this->messageKey($field));
                        }
                    }
                    if ($this->passengerData['type'] == 'infants') {
                        if ($this->departure->diffInYears($birthdate) >= $minChild || $birthdate > Carbon::now()->subDays(9) || $birthdate->isFuture()) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], 0, $minChild]), $this->messageKey($field));
                        }
                    }
                }
            }
        }
    }
}

<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics\Flight;

use App\TravelServices\Logics\Flight\PassengersValidation as FlightPassengersValidation;
use Carbon\Carbon;

trait PassengersValidation
{
    use FlightPassengersValidation {
        FlightPassengersValidation::validateBirthdateByDeparture as commonBirthDateValidate;
    }

    protected function allPassengersValidation($passenger)
    {
        $this->validateGender($passenger, 'Gender');
        $this->validateLatinNames($passenger, 'First_name');
        $this->validateLatinNames($passenger, 'Last_name');
        $this->validateBirthdateByDeparture($passenger, 'Date of birth', 2, 12);
        $this->validatePassportNumber($passenger, 'Passport Number');
        $this->validatePassportExpiry($passenger, 'Passport Expiry Date');
        $country = $this->validateCountry($passenger, 'Nationality');
        $this->validateRequired($passenger, 'National Id');
        $this->validateDateExpiry($passenger, 'NationalId Expiry Date');
        if (($country['abb'] ?? null) ==  'SA' && $this->object->seller_info['isDomesticFlight']) {
            $this->validateRequired($passenger, 'Iqama');
            $this->validateDateExpiry($passenger, 'Iqama Expiry Date');
        }
    }

    protected function validateBirthdateByDeparture($passenger, $field, $minChild, $maxChild)
    {
        $this->commonBirthDateValidate($passenger, $field, $minChild, $maxChild);
        if ($this->passengerData['type'] == 'infants') {
            try {
                $birthdate = Carbon::parse($passenger[$field] ?? null);
                if ($this->departure->diffInDays($birthdate) < 14) {
                    lugError('infant can not be younger than 14 days', [$passenger, Carbon::now()->diffInDays($birthdate)]);
                    $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], 0, $minChild]), $this->messageKey($field));
                }
            } catch (\Throwable $th) {
                //> is there are errors it's already hadled in commonBirthDateValidate
            }
        }
    }

    protected function leadPassengerValidation($passenger, $key)
    {
        $this->validateCity($passenger, 'City');
        $this->validatePhone($passenger, 'Phone');
        $this->validateEmail($passenger, 'Email');
    }


}

<?php

namespace App\TravelServices\Suppliers\Avtra\Logics\Flight;

use App\TravelServices\Logics\Flight\PassengersValidation as CommonFlightPassengersValidation;

trait PassengersValidation
{
    use CommonFlightPassengersValidation;
    protected $rph = 1, $parentRPH = 1, $infantCount, $adultsCount;

    protected function allPassengersValidation($passenger)
    {
        $this->validateGender($passenger, 'Gender');
        $this->validateLatinNames($passenger, 'First_name');
        $this->validateLatinNames($passenger, 'Last_name');
        $this->validateBirthdateByDeparture($passenger, 'Date of birth', 2, 12);
        $this->validateCountry($passenger, 'Nationality');
        $this->handleInfantAccompaniedByAdult();

        if (!$this->object->seller_info['isDomesticFlight'] ?? false) {
            $this->validatePassportExpiry($passenger, 'Passport Expiry Date');
            $this->validatePassportNumber($passenger, 'Passport Number');
            $this->validateCountry($passenger, 'Passport Issuing Country');
        }

        $this->addToPassengerData((string) $this->rph, 'rph');
        $this->rph++;
    }

    protected function leadPassengerValidation($passenger)
    {
        $this->validateEmail($passenger, 'Email');
        $this->validatePhone($passenger, 'Phone');
    }

    protected function handleInfantAccompaniedByAdult()
    {
        if ($this->passengerData['type'] == 'adults') {
            if ($this->passengerData['number'] == 1) { ///> we want this to happen once only if it's the very first adult
                $this->infantCount = $this->getServiceInfo()['query_params']['infants'] ?? 0;
            }
            if (($this->infantCount ?? 0) > 0) {
                $this->addToPassengerData(true, 'AccompaniedByInfantInd');
                $this->infantCount--;
            }
        }

        if ($this->passengerData['type'] == 'infants') {
            $this->addToPassengerData($this->parentRPH, 'parentRPH');
            $this->parentRPH++;
        }
    }
}

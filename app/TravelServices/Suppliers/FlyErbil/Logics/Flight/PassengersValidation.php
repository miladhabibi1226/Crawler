<?php

namespace App\TravelServices\Suppliers\FlyErbil\Logics\Flight;

use App\TravelServices\Logics\Flight\PassengersValidation as CommonFlightPassengersValidation;

trait PassengersValidation
{
    use CommonFlightPassengersValidation;
    protected $rph = 1;

    protected function allPassengersValidation($passenger)
    {
        $this->validateGender($passenger, 'Gender');
        $this->validateLatinNames($passenger, 'First_name');
        $this->validateLatinNames($passenger, 'Last_name');

        $this->passengersInfo[$this->passengerData['type']][$this->passengerData['key']]['rph'] = "$this->rph";
        $this->rph++;
    }

    protected function leadPassengerValidation($passenger)
    {
        $this->validateEmail($passenger, 'Email');
    }
}

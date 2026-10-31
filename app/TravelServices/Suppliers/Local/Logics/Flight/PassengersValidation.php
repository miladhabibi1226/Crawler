<?php

namespace App\TravelServices\Suppliers\Local\Logics\Flight;

use App\TravelServices\Logics\Flight\PassengersValidation as CommonFlightPassengersValidation;
use App\TravelServices\Suppliers\Local\Logics\PassengersValidation as LocalPassengersValidation;

trait PassengersValidation
{
    use CommonFlightPassengersValidation, LocalPassengersValidation {
        LocalPassengersValidation::leadPassengerValidation insteadof CommonFlightPassengersValidation;
    }

    protected function ageRange()
    {
        static $ageRange;
        if (empty($ageRange)) {
            $ageRange = explode(',', $this->object->seller_info['ageRange'] ?? ''); /// $ageRange[0] is max infant age, $ageRange[1] is max child age
        }
        return $ageRange;
    }
}

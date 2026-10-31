<?php

namespace App\TravelServices\Suppliers\Local\Logics\Hotel;

use App\TravelServices\Logics\Hotel\PassengersValidation as CommonHotelPassengersValidation;
use App\TravelServices\Suppliers\Local\Logics\PassengersValidation as LocalPassengersValidation;

trait PassengersValidation
{
    use CommonHotelPassengersValidation, LocalPassengersValidation {
        LocalPassengersValidation::leadPassengerValidation insteadof CommonHotelPassengersValidation;
    }
    
    protected function ageRange()
    {
        return explode('-', $this->object->seller_info['ageRange']);
    }
}
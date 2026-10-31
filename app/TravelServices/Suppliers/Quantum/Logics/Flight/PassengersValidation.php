<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Flight;

use App\TravelServices\Suppliers\Quantum\Logics\PassengersValidation as LogicsPassengersValidation;

trait PassengersValidation
{
    use LogicsPassengersValidation;

    protected function getFields()
    {
        // get the departure time of the last segment of the flight for PARTO validation
        $serviceInfo = $this->getServiceInfo();
        $legs = $serviceInfo['legs'];
        $lastLeg = end($legs)['segments'];
        $lastSegmentDeparture = end($lastLeg)['departure']['raw_time'] ?? null;

        $depends = [
            'referenceId' => $this->object->seller_info['bufferReferenceId'],
            'passengersInfo' => $this->objectIsBooking ? $this->object->passenger_info : request()->passengersInfo,
            'lastSegmentDeparture' => $lastSegmentDeparture ?? null,
            'type' => 'flight'
        ];
        return $depends;
    }
}

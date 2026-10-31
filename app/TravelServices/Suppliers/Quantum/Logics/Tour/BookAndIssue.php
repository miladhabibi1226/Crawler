<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Tour;

use App\TravelServices\Suppliers\Quantum\Logics\BookAndIssue as LogicsBookAndIssue;

trait BookAndIssue
{
    use LogicsBookAndIssue;

    protected function getFields()
    {
        return [
            'referenceId' => $this->object->seller_info['bufferReferenceId'],
            'passengersInfo' => $this->object->passenger_info,
            'passengersCount' => $this->object->seller_info['passengersCount'],
            'availabilityId' => $this->object->seller_info['availability']['reference_id'],
            'leader' => $this->object->leader,
            'hookUrl' => $this->hookUrl(),
            'clientBookingReference' => $this->object->reference_id
        ];
    }
}

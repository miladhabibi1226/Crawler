<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Hotel;

use App\TravelServices\Suppliers\Quantum\Logics\Book as QuantumBook;

trait Book
{
    use QuantumBook;
    protected function getFields()
    {
        return [
            'referenceId' => $this->object->seller_info['bufferReferenceId'],
            'passengersInfo' => $this->object->passenger_info,
            'selectedCombination' => $this->object->seller_info['selectedCombination'],
            'leader' => $this->object->leader,
            'hookUrl' => $this->hookUrl(),
            'clientBookingReference' => $this->object->reference_id
        ];
    }
}

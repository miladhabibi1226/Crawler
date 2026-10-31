<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Visa;

use App\TravelServices\Suppliers\Quantum\Logics\Book as QuantumBook;

trait Book
{
    use QuantumBook;
    protected function getFields()
    {
        $depends = [
            'passengersInfo' => $this->object->passenger_info,
            'leader' => $this->object->leader,
            'hookUrl' => $this->hookUrl(),
            'clientBookingReference' => $this->object->reference_id
        ];
        if ($this->workingWithACachedProfile()) {
            $cachedId = explode('/', $this->object->seller_info['cachedId']);
            $depends['cachedReference'] = $cachedId[0];
        } else {
            $depends['referenceId'] = $this->object->seller_info['bufferReferenceId'];
        }
        return $depends;
    }
}

<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Package;

use App\TravelServices\Suppliers\Quantum\Logics\BookAndIssue as LogicsBookAndIssue;

trait BookAndIssue
{
    use LogicsBookAndIssue;

    protected function getFields()
    {
        $depends = [
            'passengersInfo' => $this->object->passenger_info,
            'availability' => $this->object->seller_info['sellectedAvailability']['index'],
            'rooms' => $this->object->service_info['sellectedAvailability']['sellectedRooms'],
            'services' => $this->object->service_info['sellectedAvailability']['sellectedServices'] ?? [],
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

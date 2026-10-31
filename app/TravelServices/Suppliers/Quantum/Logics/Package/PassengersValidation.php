<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Package;

use App\TravelServices\Suppliers\Quantum\Logics\PassengersValidation as LogicsPassengersValidation;

trait PassengersValidation
{
    use LogicsPassengersValidation;

    protected function getFields()
    {
        $depends = [
            'rooms' => $this->object->service_info['sellectedAvailability']['sellectedRooms'],
            'availability' => $this->object->seller_info['sellectedAvailability']['index'],
            'passengersInfo' => $this->objectIsBooking ? $this->object->passenger_info : request()->passengersInfo,
            'type' => 'package'
        ];
        if (!empty($this->object->seller_info['bookingReferenceId'])) { ///> this happens when we are validating passengers info from the backOffice
            $depends['bookingReferenceId'] = $this->object->seller_info['bookingReferenceId'];
        } else if ($this->workingWithACachedProfile()) { ///> this happens when we are validating passengers info from a cached buffer
            $cachedId = explode('/', $this->object->seller_info['cachedId']);
            $depends['cachedReference'] = $cachedId[0];
        } else { ///> this happens when we are validating passengers info from a searched database buffer
            $depends['referenceId'] = $this->object->seller_info['bufferReferenceId'];
        }
        return $depends;
    }
}

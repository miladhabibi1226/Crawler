<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Visa;

use App\TravelServices\Suppliers\Quantum\Logics\PassengersValidation as LogicsPassengersValidation;

trait PassengersValidation
{
    use LogicsPassengersValidation;

    protected function mapper()
    {
        if ($this->objectIsBuffer) {
            return ['status' => true, 'passengersInfo' => $this->items['passengersInfo'], 'leader' => $this->items['leader']];
        } else {
            $this->object->leader = $this->items['leader'];
            if (!empty($this->items['passengersInfo'])) {
                $this->object->passenger_info = $this->items['passengersInfo'];
            }
            foreach ($this->items['passengersInfo'] as $passenger) {
                $documents[] = [
                    'first_name' => $passenger[$this->getPassengerFormCaption('first_name')] ?? '',
                    'last_name' => $passenger[$this->getPassengerFormCaption('last_name')] ?? '',
                    'visa_status' => 'under_process',
                    'documents' => [],
                    'comments' => []
                ];
            }
            $this->object->booking_info = array_merge($this->object->booking_info, ['passengers' => $documents ?? []]);
            $this->object->update();
        }
    }
}

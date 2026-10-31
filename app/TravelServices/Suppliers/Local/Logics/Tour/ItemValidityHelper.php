<?php

namespace App\TravelServices\Suppliers\Local\Logics\Tour;

use Carbon\Carbon;

trait ItemValidityHelper
{
    protected function getRawResult()
    {
        if ($this->objectIsBooking) {
            $availabilityId = $this->object->seller_info['availability']['id'] ?? null;
        } else {
            $availabilityId = request('availability');
        }
        $availabilityModel = 'MatinUtils\NodeTracker\Models\\' . ucfirst($this->travelService) . 'Availability';
        $availability = $availabilityModel::find($availabilityId);
        $this->rawResult = [
            'availability' => $availability,
            'passengersCount' => $this->object->seller_info['passengersCount'] ?? []
        ];
    }

    protected function checkAvailabilityAndPricing()
    {
        // Checking the remaining capacity
        $remainCapacity = $this->rawResult['availability']->remaining_capacity;
        $passengersCount = array_sum($this->rawResult['passengersCount']);
        if ($passengersCount > $remainCapacity) {
            lugError('The remain capacity for the availability is not enough for the number of the passengers.', [$this->rawResult]);
            $this->addError('Remain Capacity is not enough.');
            if ($this->objectIsBooking) {
                $this->addErrorToBook(trans('messages.capacity'));
            }
        }

        // Checking the date
        $from = $this->rawResult['availability']->from;
        if ($from < Carbon::now()) {
            lugError('Booking an outdated availability', [$this->rawResult]);
            $this->addError('Booking an outdated availability.');
            if ($this->objectIsBooking) {
                $this->addErrorToBook(trans('messages.outdated'));
            }
        }
    }
}

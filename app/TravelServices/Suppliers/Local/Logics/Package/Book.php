<?php

namespace App\TravelServices\Suppliers\Local\Logics\Package;

use App\TravelServices\CachedBuffer;
use Carbon\Carbon;
use App\TravelServices\Logics\Book as LogicsBook;

trait Book
{
    use LogicsBook, ItemValidityHelper;
    protected $availability, $passengersCount;

    protected function getContent()
    {
        $this->getRawResult();
        // lugInfo("Local Package Book getContent items", $this->rawResult ?? []);
    }

    protected function extractor()
    {
        $this->checkAvailabilityAndPricing();
        $this->items['bookingInfo'] = [
            'pnr' => convertId($this->object->id),
            'expires_at' => Carbon::now()->addHours(2)
        ];
        lugInfo("Local package Book extractor Items", ['items' => $this->items]);
    }

    protected function updateLocalItems()
    {
        ///> this is part of the mapper
        ///> updating used availabilities
        $this->updateFlightAllocations($this->rawResult['flightAllocations']);
        $this->updateAvailability();
    }

    protected function updateAvailability()
    {
        $availabilityRooms = $this->rawResult['availability']->rooms;
        $bookedRooms = $this->rawResult['sellectedAvailability']['sellectedRooms'];
        foreach ($availabilityRooms as $key => $roomInfo) {
            $roomType = $roomInfo['name'];
            if (!empty($bookedRooms[$roomType])) {
                if ($roomInfo['quantity'] != 'unlimited') {
                    $availabilityRooms[$key]['quantity'] = $roomInfo['quantity'] - count($bookedRooms[$roomType]);
                }
            }
        }
        $this->rawResult['availability']->rooms  = $availabilityRooms;
        $this->rawResult['availability']->remaining_capacity  = $this->rawResult['availability']->remaining_capacity - ($this->rawResult['passengersCountSum'] - $this->rawResult['infantsCount']);
        $this->rawResult['availability']->update();
        (new CachedBuffer('package', $this->rawResult['availability']->package_id, $this->seller))->clearCachedBuffer();
        lugInfo('Updated availability', ['remaining_capacity' => $this->rawResult['availability']->remaining_capacity, 'rooms' => $this->rawResult['availability']->rooms]);
    }

    protected function updateFlightAllocations($flightAllocations)
    {
        if (count($flightAllocations) > 0) {
            $flightPassengersInfo = [];
            foreach ($this->object->passenger_info as $roomType => $roomTypeGroup) {
                foreach ($roomTypeGroup as $roomIndex => $roomPassengers) {
                    foreach ($roomPassengers as $passengerType => $passengers) {
                        $passengerType = ($passengerType == 'childrenWOBed') ? 'children' : $passengerType;
                        foreach ($passengers as $passengerIndex => $passenger) {
                            $passenger['first_name'] = $passenger[$this->getPassengerFormCaption('first_name')];
                            $passenger['last_name'] = $passenger[$this->getPassengerFormCaption('last_name')];
                            if (!empty($this->object->seller_info['passengersForm']['all']['title'])) {
                                $passenger['title'] = $passenger['title'];
                            }
                            $passenger['roomType'] = $roomType;
                            $passenger['roomIndex'] = $roomIndex;
                            $passenger['passengerIndex'] = $passengerIndex;
                            $flightPassengersInfo[$passengerType][] = $passenger;
                        }
                    }
                }
            }
            foreach ($flightAllocations as $flightAllocation) {
                $flightAllocation->passengers_info = ($flightAllocation->passengers_info ?? []) + [$this->object->id => $flightPassengersInfo];
                $flightAllocation->update();
            }
            lugInfo('Updated Flight allocations', ['package passengers' => $flightPassengersInfo, 'Flight allocations' => $flightAllocations]);
        }
    }

    protected function failed($error = null)
    {
        $this->failBook();
        return ['status' => false];
    }
}

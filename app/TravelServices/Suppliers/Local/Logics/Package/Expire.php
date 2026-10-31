<?php

namespace App\TravelServices\Suppliers\Local\Logics\Package;

use App\TravelServices\CachedBuffer;
use App\TravelServices\Suppliers\Local\Logics\Expire as LogicsExpire;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\Invoice;
use MatinUtils\NodeTracker\Models\PackageAvailability;

trait Expire
{
    use LogicsExpire;

    protected function mapper()
    {
        $availability = PackageAvailability::find($this->object->seller_info['sellectedAvailability']['availabilityId'] ?? $this->object->price_info['sellectedAvailability']['availabilityId'] ?? null);
        $this->updateFlightAllocations($availability?->flights ?? []);
        $this->updateAvailability($availability);
        
        $bookingInfo = $this->object->booking_info + ['expired_at' => Carbon::now()];
        $this->object->booking_info = $bookingInfo;
        $this->object->status =  'expired';
        $this->object->update();

        $invoice = Invoice::find($this->object->invoiceItems->first()->invoice_id);
        if (!in_array($invoice->payment_status, ['paid', 'confirmed'])) {
            $invoice->payment_status = 'not_authorized';
            $invoice->update();
        }
    }

    protected function updateAvailability($availability)
    {
        $bookedRooms = $this->object->service_info['sellectedAvailability']['sellectedRooms'];
        $availabilityRooms = $availability->rooms;
        foreach ($availabilityRooms as $key => $roomInfo) {
            $roomType = $roomInfo['name'];
            if (!empty($bookedRooms[$roomType])) {
                if ($roomInfo['quantity'] != 'unlimited') {
                    $availabilityRooms[$key]['quantity'] = $roomInfo['quantity'] + count($bookedRooms[$roomType]);
                }
            }
        }
        $availability->rooms = $availabilityRooms;
        $passengersCount = $this->packagePassengerCountSum($this->object->service_info['passengersCount']);
        $oldCapacity = $availability->remaining_capacity;
        $availability->remaining_capacity = $oldCapacity + ($passengersCount['total'] - $passengersCount['infants']);
        $availability->update();
        (new CachedBuffer('package', $availability->package_id, $this->seller))->clearCachedBuffer();
        lugInfo('increase remaining capacity', ['new' => $availability->remaining_capacity, 'old' => $oldCapacity]);
    }

    protected function updateFlightAllocations($flights)
    {
        $flightAllocations = $this->getFlightAllocationsForPackage($flights);
        foreach ($flightAllocations as $flightAllocation) {
            $flightPassengersInfo = $flightAllocation->passengers_info;
            unset($flightPassengersInfo[$this->object->id]);
            $flightAllocation->passengers_info = $flightPassengersInfo;
            $flightAllocation->update();
        }
    }
}

<?php

namespace App\TravelServices\Suppliers\Local\Logics\Tour;

use App\TravelServices\Suppliers\Local\Logics\Expire as LogicsExpire;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\Invoice;

trait Expire
{
    use LogicsExpire;

    protected function mapper()
    {
        $availabilityModel = 'MatinUtils\NodeTracker\Models\\' . ucfirst($this->travelService) . 'Availability';
        $passengersCount = array_sum(array_values($this->object->seller_info['passengersCount']));
        $availability = $availabilityModel::find($this->object->seller_info['availability']['id']);
        $oldCapacity = $availability->remaining_capacity;
        $availability->remaining_capacity = $oldCapacity + $passengersCount;
        $availability->update();
        lugInfo('increase remaining capacity', ['new' => $availability->remaining_capacity, 'old' => $oldCapacity]);

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
}

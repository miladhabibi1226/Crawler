<?php

namespace App\TravelServices\Suppliers\Local\Logics\Flight;

use App\TravelServices\Suppliers\Local\Logics\Expire as LogicsExpire;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\FlightPricing;
use MatinUtils\NodeTracker\Models\Invoice;

trait Expire
{
    use LogicsExpire;

    protected function mapper()
    {
        ///> if the flight is a roundtrip we have 2 pricings
        foreach ($this->object->price_info['sellerPrice']['rawPrice'] as $legPricings) {
            foreach ($legPricings as $segPricing) {
                $pricingids[] = $segPricing['id'];
            }
        }
        $pricings = FlightPricing::whereIn('id', $pricingids ?? [])->get();
        foreach ($pricings as $pricing) {
            $passengersCount = $this->object->service_info['passengersCount']['adults'] + ($this->object->service_info['passengersCount']['children'] ?? 0);
            $oldCapacity = $pricing->remaining;
            $pricing->remaining = $oldCapacity + $passengersCount;
            $pricing->sold = $pricing->sold - $passengersCount;
            $pricing->update();
            lugInfo('increase remaining capacity', ['new' => $pricing->remaining, 'old' => $oldCapacity]);
        }

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

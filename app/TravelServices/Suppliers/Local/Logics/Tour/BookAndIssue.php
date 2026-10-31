<?php

namespace App\TravelServices\Suppliers\Local\Logics\Tour;

use Carbon\Carbon;

trait BookAndIssue
{
    use Book;

    protected function extractor()
    {
        $this->checkAvailabilityAndPricing();
        $this->items['bookingInfo'] = [
            'pnr' => convertId($this->object->id),
            'issued_at' => Carbon::now()
        ];
        lugInfo("Local Tour BookAndIssue extractor Items", ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->updateLocalItems();
        $this->object->booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingInfo'] ?? []);
        $this->object->status =  'issued';
        $this->object->booked_at = Carbon::now();
        $this->object->pnr = $this->items['bookingInfo']['pnr'] . ':';
        $this->object->update();
        $this->itemIssued($this->object->booking_info['pnr']);
    }

    protected function done()
    {
        $this->sellerTransaction();
        return ['status' => true];
    }
}

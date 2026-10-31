<?php

namespace App\TravelServices\Suppliers\Local\Logics\Visa;

use Carbon\Carbon;

trait BookAndIssue
{
    use Book;
    protected function extractor()
    {
        $this->items['bookingInfo'] = [
            'pnr' => convertId($this->object->id),
            'issued_at' => Carbon::now()
        ];
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingInfo'] ?? []);
        $this->object->status =  'waiting_issue';
        $this->object->booked_at = Carbon::now();
        $this->object->pnr = $this->items['bookingInfo']['pnr'] . ':';
        $this->object->update();
        $this->itemIssued($this->items['bookingInfo']['pnr']);
        sendVisaRequestEmails($this->object);
    }
    ///> no seller transaction bc status is only waiting_issue
}

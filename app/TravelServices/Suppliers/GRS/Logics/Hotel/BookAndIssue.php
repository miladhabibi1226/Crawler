<?php

namespace App\TravelServices\Suppliers\GRS\Logics\Hotel;

use App\TravelServices\Handler;
use Carbon\Carbon;

trait BookAndIssue
{
    use Book;

    protected function mapper()
    {
        $this->object->booking_info = $this->items['bookingInfo'];
        $this->object->status = strtoupper($this->rawResult['decoded']['value']['reserve']['status'] ?? '') == 'BOOKING' ? 'validated' : ($this->bookingType == 'bookOnly' ? 'on_hold' : 'issued');
        $this->object->booked_at = Carbon::now();
        $this->object->pnr = $this->items['bookingInfo']['pnr'] . ':';
        $this->object->update();
        if ($this->object->status == 'issued') {
            $this->itemIssued($this->items['bookingInfo']['pnr'] ?? []);
        }
    }

    public function done()
    {
        return Handler::issue($this->object->reference_id);
    }
}

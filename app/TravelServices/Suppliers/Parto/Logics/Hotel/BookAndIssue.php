<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Hotel;

use App\TravelServices\Handler;
use Carbon\Carbon;

trait BookAndIssue
{
    use Book;
    protected function mapper()
    {
        if ($this->items['sellerInfo']['HotelBookResult']['Status'] == 20 && Carbon::parse($BookResult['PaymentDeadline'] ?? null) < Carbon::now()) { ///> 20 is CONFIRM
            $goingToCallIssue = false;
            $this->sellerTransaction();
        }
        $this->object->booking_info = $this->items['bookingInfo'];
        $this->object->status = ($goingToCallIssue ?? true) ? 'validated' : 'issued';
        $this->object->booked_at = Carbon::now();
        $this->object->pnr = $this->items['bookingInfo']['pnr'] . ':';
        $this->object->update();
        if ($this->object->status == 'issued') {
            $this->itemIssued($this->items['bookingInfo']['pnr'] ?? []);
        }
    }

    public function done()
    {
        $this->object->refresh();
        if ($this->object->status == 'validated') {
            return Handler::issue($this->object->reference_id);
        }
        return ['status' => true];
    }
}

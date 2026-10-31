<?php

namespace App\TravelServices\Logics\Flight;

use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\Invoice;

trait Expire
{
    ///> All the suppliers for all the services use this class entirely, except locals suppliers
    protected function canBeDone()
    {
        if ($this->object->status != 'on_hold') {
            $this->addError('Invalid Booking status: ' . $this->object->status);
        }
        return true;
    }

    protected function mapper()
    {
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

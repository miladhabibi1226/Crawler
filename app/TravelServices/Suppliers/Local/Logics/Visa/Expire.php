<?php

namespace App\TravelServices\Suppliers\Local\Logics\Visa;

use App\TravelServices\Suppliers\Local\Logics\Expire as LogicsExpire;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\Invoice;

trait Expire
{
    use LogicsExpire;

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

<?php

namespace App\TravelServices\Suppliers\Local\Logics\Visa;

use App\TravelServices\Suppliers\Local\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;

    protected function mapper()
    {
        ///> set the booking status to waiting_issue. it will  be set to 'issued' by the booking controller in the panel
        $this->object->booking_info = array_merge($this->object->booking_info, ['issued_at' => Carbon::now()]);
        $this->object->status =  'waiting_issue';
        $this->object->update();
        $this->itemIssued($this->object->booking_info['pnr']);
        sendVisaRequestEmails($this->object);
    }
}

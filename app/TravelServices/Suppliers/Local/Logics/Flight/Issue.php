<?php

namespace App\TravelServices\Suppliers\Local\Logics\Flight;

use App\Jobs\LocalTicketNumber;
use App\TravelServices\Suppliers\Local\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;

    protected function mapper()
    {
        ///> we use a queue to make sure tickets are unique (if 2 bookings are being issued at the exact same time)
        $this->object->booking_info = array_merge($this->object->booking_info, ['issued_at' => Carbon::now()]);
        $this->object->status = 'waiting_issue';
        $this->object->update();
        dispatch(new LocalTicketNumber(node()->id, $this->object->id, app('log-system')->getPid()))->onQueue('flight');
    }
}

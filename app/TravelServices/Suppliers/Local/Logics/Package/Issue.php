<?php

namespace App\TravelServices\Suppliers\Local\Logics\Package;

use App\TravelServices\Suppliers\Local\Logics\Issue as LogicsIssue;
use Carbon\Carbon;
use App\Jobs\LocalTicketNumber;

trait Issue
{
    use LogicsIssue;

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info, ['issued_at' => Carbon::now()]);
        $this->object->status =  'issued';
        $this->object->update();
        $this->itemIssued($this->object->booking_info['pnr']);
        dispatch(new LocalTicketNumber(node()->id, $this->object->id, app('log-system')->getPid()))->onQueue('flight');
    }
}

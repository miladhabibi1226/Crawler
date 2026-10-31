<?php

namespace App\TravelServices\Suppliers\Local\Logics;

use App\TravelServices\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info, ['issued_at' => Carbon::now()]);
        $this->object->status =  'issued';
        $this->object->update();
        $this->itemIssued($this->object->booking_info['pnr']);
    }
}

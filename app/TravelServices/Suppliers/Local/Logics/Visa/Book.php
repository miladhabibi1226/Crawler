<?php

namespace App\TravelServices\Suppliers\Local\Logics\Visa;

use App\TravelServices\Logics\Book as LogicsBook;
use Carbon\Carbon;

trait Book
{
    use LogicsBook;

    protected function getContent()
    {
    }

    protected function extractor()
    {
        $this->items['bookingInfo'] = [
            'pnr' => convertId($this->object->id),
            'expires_at' => Carbon::now()->addHours(2)
        ];
    }

    protected function updateLocalItems()
    {
        ///> this is part of the mapper
        ///> visa has nothis to update
    }

    protected function failed($error = NULL)
    {
        $this->failBook();
        return ['status' => false, 'bookingStatus' => $this->object->status];
    }
}

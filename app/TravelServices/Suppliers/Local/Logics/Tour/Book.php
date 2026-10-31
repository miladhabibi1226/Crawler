<?php

namespace App\TravelServices\Suppliers\Local\Logics\Tour;

use App\TravelServices\Logics\Book as LogicsBook;
use Carbon\Carbon;

trait Book
{
    use LogicsBook, ItemValidityHelper;

    protected function getContent()
    {
        $this->getRawResult();
        // lugInfo("Local Tour Book getContent items", $this->rawResult ?? []);
    }

    protected function extractor()
    {
        $this->checkAvailabilityAndPricing();
        $this->items['bookingInfo'] = [
            'pnr' => convertId($this->object->id),
            'expires_at' => Carbon::now()->addHours(2)
        ];
        lugInfo("Local Tour Book extractor Items", ['items' => $this->items]);
    }

    protected function updateLocalItems()
    {
        ///> this is part of the mapper
        ///> updating used availabilities
        $originalCapacity = $this->rawResult['availability']->remaining_capacity;
        $this->rawResult['availability']->remaining_capacity = $originalCapacity - array_sum($this->rawResult['passengersCount']);
        $this->rawResult['availability']->update();
        lugInfo('Updated availability', [$this->rawResult['availability']]);
    }

    protected function failed($error = null)
    {
        $this->failBook();
        return ['status' => false];
    }
}

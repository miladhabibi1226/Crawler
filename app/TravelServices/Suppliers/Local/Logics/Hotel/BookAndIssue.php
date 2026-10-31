<?php

namespace App\TravelServices\Suppliers\Local\Logics\Hotel;

use Carbon\Carbon;

trait BookAndIssue
{
    use AvailabilityAndPricing {
        ///> using AvailabilityAndPricing for getContent and extractor, using HotelBook for canBeDone mapper and getExpiryTime
        AvailabilityAndPricing::extractor as AvailabilityAndPricingExtractor;
    }

    protected function extractor()
    {
        $this->AvailabilityAndPricingExtractor();
        $this->items['bookingResult'] = [
            'pnr' => convertId($this->object->id),
            'issued_at' => Carbon::now()
        ];
        lugInfo("Local Hotel $this->action extractor Items", ['items' => $this->items]);
    }

    protected function updateLocalItems()
    {
        ///> this is part of the mapper
        foreach ($this->rawResult as $roomsPricings) {
            foreach ($roomsPricings['datesPriceList'] as $availabilityInfo) {
                $availability = $availabilityInfo['newAvailability'];
                $availability->status = 'occupied';
                $availability->update();
            }
        }
    }
    protected function mapper() ///> This method will be used for bookAndIssue as well
    {
        $this->updateLocalItems();
        $this->object->refresh();
        $this->object->booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingResult'] ?? []);
        $this->object->status = 'issued';
        $this->object->booked_at = Carbon::now();
        $this->object->pnr = $this->items['bookingResult']['pnr'] . ':';
        $this->object->update();
        $this->itemIssued($this->items['bookingResult']['pnr'] ?? []);
    }

    protected function failed($error = NULL)
    {
        $this->failBook();
        return ['status' => false, 'bookingStatus' => $this->object->status];
    }

    protected function done()
    {
        $this->sellerTransaction();
        return ['status' => true];
    }
}

<?php

namespace App\TravelServices\Suppliers\Local\Logics\Hotel;

use App\TravelServices\Logics\Hotel\Book as HotelBook;

trait Book
{
    use HotelBook, AvailabilityAndPricing {
    ///> using AvailabilityAndPricing for getContent and extractor, using HotelBook for canBeDone mapper and getExpiryTime
        HotelBook::mapper insteadof AvailabilityAndPricing;
        AvailabilityAndPricing::getContent insteadof HotelBook;
        AvailabilityAndPricing::extractor as AvailabilityAndPricingExtractor;
    }

    protected function extractor()
    {
        $this->AvailabilityAndPricingExtractor();
        $this->items['bookingInfo'] = [
            'pnr' => convertId($this->object->id),
            'expires_at' => $this->getExpiryTime()
        ];
        lugInfo("Local Hotel Book extractor Items", ['items' => $this->items]);
    }

    protected function updateLocalItems()
    {
        ///> this is part of the mapper
        ///> updating used availabilities
        foreach ($this->rawResult as $roomsPricings) {
            foreach ($roomsPricings['datesPriceList'] as $availabilityInfo) {
                $availability = $availabilityInfo['newAvailability'];
                $availability->status = 'occupied';
                $availability->update();
            }
        }
    }

    protected function failed($error = NULL)
    {
        $this->failBook();
        return ['status' => false, 'bookingStatus' => $this->object->status];
    }
}

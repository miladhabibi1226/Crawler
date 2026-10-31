<?php

namespace App\TravelServices\Suppliers\Local\Logics\Flight;

use App\TravelServices\Logics\Flight\Book as FlightBook;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\FlightPricing;

trait Book
{
    use FlightBook;
    protected $pricings, $passengersCount, $sementsCount;
    protected function getContent()
    {
        ///> we have one pricing for each segment of each leg
        foreach ($this->object->price_info['sellerPrice']['rawPrice'] as $legPricings) {
            foreach ($legPricings as $segPricing) {
                $pricingids[] = $segPricing['id'];
            }
        }
        $this->pricings = FlightPricing::whereIn('id', $pricingids)->get();
        $this->passengersCount = $this->object->service_info['passengersCount']['adults'] + ($this->object->service_info['passengersCount']['children'] ?? 0);
        $this->sementsCount = count($pricingids);
    }

    protected function extractor()
    {
        if (count($this->pricings) != $this->sementsCount) {
            lugError('at least one pricing is not available anymore', ['retreived pricings' => $this->pricings, 'old rawPricing' => $this->object->price_info['sellerPrice']['rawPrice']]);
            $this->addError('at least one pricing is not available anymore');
            $this->addErrorToBook(trans('messages.unavailable'));
        }
        foreach ($this->pricings as $pricing) {
            if ($pricing->remaining < $this->passengersCount) {
                lugError('Not enough capacity', ['remaining' => $pricing->remaining, 'passengersCount' => $this->passengersCount]);
                $this->addError('Not enough capacity');
                $this->addErrorToBook(trans('messages.capacity'));
            }
        }
        $this->items['bookingInfo'] = [
            'pnr' => (array) convertId($this->object->id),
            'expires_at' =>  Carbon::now()->endOfDay()
        ];
        lugInfo("Local flight Book extractor Items", ['items' => $this->items]);
    }

    protected function updateLocalItems()
    {
        ///> this is part of the mapper
        foreach ($this->pricings as $pricing) {
            $pricing->remaining =  $pricing->remaining - $this->passengersCount;
            $pricing->sold =  $pricing->sold + $this->passengersCount;
            $pricing->update();
        }
    }

    protected function failed($error = NULL)
    {
        $this->failBook();
        return ['status' => false, 'bookingStatus' => $this->object->status];
    }
}

<?php

namespace App\TravelServices\Suppliers\Local\Logics\Package;

use App\TravelServices\Logics\Availabilities as LogicsAvailabilities;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\PackageAvailability;

trait Availabilities
{
    use LogicsAvailabilities;
    protected function getContent()
    {
        $this->accessListExceptions = $this->seller->accessExceptions()->get();
        $exceptions = $this->availabilityIdsFromExceptins();
        $this->rawResult = PackageAvailability::where('package_id', $this->object->package_profile_id)
            ->when(!empty($exceptions) && !$this->seller->is_allow_all, function ($query) use ($exceptions) {
                $query->whereIn('reference_id', $exceptions);
            })->when(!empty($exceptions) && $this->seller->is_allow_all, function ($query) use ($exceptions) {
                $query->whereNotIn('reference_id', $exceptions);
            })->where('from', '>=', Carbon::now()->addDays($this->object->seller_info['registerLimit'] ?? 0))->get();
    }

    protected function availabilityIdsFromExceptins()
    {
        foreach ($this->accessListExceptions as $exception) {
            if (!empty($exception->conditions['availability_id'])) {
                $availabilityIds[] = $exception->conditions['availability_id'];
            }
        }
        return $availabilityIds ?? [];
    }

    protected function extractor()
    {
        $items = [];
        foreach ($this->rawResult as $key => $availability) {
            $rooms = $availability->rooms;
            if (empty($rooms)) {
                continue;
            }
            $roomDetails = $roomPricing = [];
            foreach ($rooms as $room) {
                $roomDetails[$room['name']] = [
                    'quantity' => $room['quantity'] ?? 0,
                    'beds' => (int) $room['beds'] ?? 0,
                    'minParticipants' => (int) $room['minParticipants'] ?? 0,
                    'extraBed1' => $room['extraBed1'] ?? 'no',
                    'extraBed2' => $room['extraBed2'] ?? 'no',
                    'extraBed3' => $room['extraBed3'] ?? 'no',
                    'childWOBed' => (int) $room['childWOBed'],
                    'note' => $room['note']
                ];
                $roomPricing[$room['name']] = $room['pricing'];
            }
            $items['serviceInfo'][$key] = [
                'from' => $availability->from,
                'to' => $availability->to,
                'descriptions' => $availability->descriptions,
                'capacity' => $availability->remaining_capacity,
                'rooms'  => $roomDetails,
                'ageRange' => $availability->age_range,
                'hotelDetails' => $availability->hotel_details,
                'tripLength' => Carbon::parse($availability->from)->diffInDays(Carbon::parse($availability->to)->addDay()),
            ];
            $items['priceInfo'][$key] = [
                'discount' => $availability->discount,
                'rooms' => $roomPricing,
                'conditions' => ['availability_id' => $availability->reference_id, 'profile_id' => $availability->package->reference_id]
            ];
            $items['sellerInfo'][$key] = [
                'availabilityId' => $availability->id,
                'flights' => $availability->flights
            ];
        }
        if (empty($items)) {
            lugError('No availability', [$this->rawResult, $this->accessListExceptions]);
            return $this->addError('No availability');
        }
        $items = $this->extraDatafromPlugin($items);
        $this->items['priceInfo'] = $this->afterPriceModifier($this->priceModifier($items), ['availabilitiesSellerInfo' => $items['sellerInfo']]);
        if (empty($this->items['priceInfo'])) {
            return $this->addError('Pricing Info Unavailable');
        }
        $this->items['serviceInfo'] = $this->includeMinPriceInServiceInfo($items['serviceInfo'], $this->items['priceInfo']);
        $this->items['sellerInfo'] = $items['sellerInfo'];
        $this->sortAvailabilitiesBydateAndPrice();
        lugInfo('Local package availabilities extractor items', []);
    }

    protected function mapper()
    {
        $service = $this->object->service_info;
        $service['availabilities'] = $this->items['serviceInfo'];
        $this->object->service_info = $service;
        $seller = $this->object->seller_info;
        $seller['afters']['availabilities'] = true;
        if (!empty($this->items['sellerInfo'])) {
            $seller['availabilities'] = $this->items['sellerInfo'];
        }
        $this->object->seller_info = $seller;
        $price = $this->object->price_info;
        $price['availabilities'] = $this->items['priceInfo'];
        $this->object->price_info = $price;
        $this->updateObject();
    }

    protected function getQueryParams()
    {
        return $this->object->service_info['query_params'];
    }

    protected function priceModifier($items)
    {
        return $this->calculatePriceforAvailabilities([
            'priceInfo' => $items['priceInfo'],
            'sellerInfo' => $items['sellerInfo'],
        ]);
    }

    protected function includeMinPriceInServiceInfo($serviceInfo, $priceInfo)
    {
        foreach ($serviceInfo as $index => $availability) {
            $minPrice = 0;
            foreach ($priceInfo[$index]['rooms'] as $roomPriceInfo) {
                $roomAdultPrice = $roomPriceInfo['marketplacePrice']['payable']['adult'];
                $minPrice = empty($minPrice) ? $roomAdultPrice : ($minPrice < $roomAdultPrice ? $minPrice : $roomAdultPrice);
            }
            $serviceInfo[$index]['minPrice'] = $minPrice;
        }
        return $serviceInfo;
    }

    protected function sortAvailabilitiesBydateAndPrice()
    {
        foreach ($this->items['serviceInfo'] as $index => $availability) {
            $minPrice = $availability['minPrice'];
            while (!empty($sortables[$availability['from'] . ":$minPrice"])) {
                $minPrice += 1;
            }
            $sortables[$availability['from'] . ":$minPrice"] = $index;
        }
        ksort($sortables);
        foreach ($sortables ?? [] as $sortableIndex => $itemIndex) {
            $newServiceInfo[] = $this->items['serviceInfo'][$itemIndex];
            $newSellerInfo[] = $this->items['sellerInfo'][$itemIndex];
            $newPriceInfo[] = $this->items['priceInfo'][$itemIndex];
        }
        $this->items['serviceInfo'] = $newServiceInfo ?? [];
        $this->items['sellerInfo'] = $newSellerInfo ?? [];
        $this->items['priceInfo'] = $newPriceInfo ?? [];
    }

    protected function updateObject()
    {
        if ($this->isObjectBuffer()) {
            return $this->object->update();
        }
        $this->object->updateCached(); ///> could have named it "update" but i didn't want to mix it with model::update for now
    }
}

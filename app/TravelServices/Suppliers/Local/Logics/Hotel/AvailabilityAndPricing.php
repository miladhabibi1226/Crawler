<?php

namespace App\TravelServices\Suppliers\Local\Logics\Hotel;

use MatinUtils\NodeTracker\Models\HotelAvailability;

trait AvailabilityAndPricing
{
    protected $passengerCount;
    protected function getContent()
    {
        $rooms = $this->object->price_info['selectedCombination']['sellerPrice']['rawPrice'];

        foreach ($rooms as $roomsPricings) {
            foreach ($roomsPricings['datesPriceList'] as $availability) {
                $availabilityIds[] = $availability['availability_id'];
            }
        }

        $availabilities = HotelAvailability::whereIn('id', $availabilityIds)->with('roomTypePricing')->get();
        foreach ($rooms as $index => $roompriceInfo) {
            foreach ($roompriceInfo['datesPriceList'] as $key => $availabilityInfo) {
                $rooms[$index]['datesPriceList'][$key]['newAvailability'] = $availabilities->where('id', $availabilityInfo['availability_id'])->first();
            }
        }
        $this->rawResult = $rooms;
        $this->passengerCount = $this->object->service_info['passengerCount'];
        lugInfo("Local Hotel $this->action getContent items", [
            // 'rawResult' => $this->rawResult,
            'passengerCount' => $this->passengerCount
        ]);
    }

    protected function extractor()
    {
        foreach ($this->rawResult as $roomIndex => $roomPricings) {
            $adultPrice = 0;
            $childPrice = 0;
            foreach ($roomPricings['datesPriceList'] as $index => $availabilityInfo) {
                if (($availabilityInfo['newAvailability']?->status ?? null) != 'open') {
                    $this->addError('status not open anymore');
                    $this->addErrorToBook(trans('messages.unavailableHotel'));
                    lugError('status not open anymore', [$roomPricings]);
                    return;
                }
                $newInclusions = $availabilityInfo['newAvailability']->roomTypePricing->inclusions ?? [];
                foreach ($newInclusions as $inclusion) {
                    if ($inclusion['type'] == $roomPricings['inclusion']['type']) {
                        if ($inclusion['status'] ?? false) {
                            $adultPrice += $inclusion['adultPrice'] ?? 0;
                            $childPrice += $inclusion['childPrice'] ?? 0;
                        } else {
                            $this->addError('inclusion status not active anymore');
                            $this->addErrorToBook(trans('messages.unavailableHotel'));
                            lugError('inclusion status not active anymore', [$roomPricings]);
                        }
                        break;
                    }
                }
            }
            if ($roomPricings['inclusion']['adultPrice'] != $adultPrice || $roomPricings['inclusion']['childPrice'] != $childPrice) {
                $this->addError('pricing change');
                $this->addErrorToBook(trans('messages.unavailableHotel'));
                lugError('pricing change', [$roomPricings]);
            }
        }
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['beforeBooks']['AvailabilityAndPricing'] = true;
        $this->object->seller_info = $sellerInfo;
        $this->object->update();
    }

    protected function failed($error = null)
    {
        if ($this->objectIsBooking) {
            $this->failBook();
        }
        return ['status' => false];
    }
}

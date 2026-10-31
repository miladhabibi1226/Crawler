<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\New;

trait GetRoomInformation
{
    use AvailabilityAndPricing;

    public function checkStartStatus()
    {
        return true;
    }

    public function extractor()
    {
        $selectedCombinations = [];
        $result = $this->rawResult['decoded']['HotelResult'][0];
        //empty result
        if (empty($result)) {
            $this->addError('response is invalid');
            $this->addErrorToBook(trans('response is invalid'));
            lugError('empty result', [$result]);
            return $this->failBook();
        }
        $this->items['serviceInfo'] =  $this->object->service_info;
        $selectedCombinations = $this->objectIsBooking ? $this->object->service_info['selectedCombination'] : request()->serviceInfoSelectedCombination;
        foreach ($selectedCombinations as $key => $selectedCombination) {
            foreach ($result['Rooms'][0]['Name'] as $roomName) {
                if ($roomName == $selectedCombination['name']) {
                    $this->items['serviceInfo']['selectedCombination'][$key]['Inclusion'] = $result['Rooms'][0]['Inclusion'] ?? $selectedCombination['inclusion'];
                    $this->items['serviceInfo']['selectedCombination'][$key]['promtion'] = $result['Rooms'][0]['RoomPromotion'] ??  $selectedCombination['promtion'];
                }
            }
        }
        $this->items['serviceInfo']['conditions'] =  $result['RateConditions'] ?? [];
        lugInfo('TBO create book Extractor items', [$this->items]);
    }

    protected function mapper()
    {
        $this->object->service_info = array_merge($this->object->service_info ?? [], $this->items['serviceInfo'] ?? []);
        $this->object->update();
    }
}

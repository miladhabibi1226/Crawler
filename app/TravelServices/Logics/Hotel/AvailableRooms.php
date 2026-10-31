<?php

namespace App\TravelServices\Logics\Hotel;

use App\TravelServices\Logics\Availabilities;

trait AvailableRooms
{
    use Availabilities;
    protected $items = [], $roomsPriceList = [];
    protected function extractor()
    {
        $this->items['roomDetail'] = $this->roomMapper(); ///> for service info
        $roomCombinations = $this->roomCombinationsModifier();
        $this->items['combinationsInfo'] = $roomCombinations['roomCombinations'] ?? []; ///> will be used in booking (key is important)
        $this->items['filters'] = $this->createFilters();
        $this->items['combinationsPrice'] = $this->priceModifier($roomCombinations['combinationsInfo'] ?? [], $this->object->price_info['exceptionInfo']); ///> for price info
        if (empty($this->items['combinationsPrice'])) {
            return $this->addError('Pricing Info Unavailable');
        }
        lugInfo($this->supplierName . ' hotel AvailableRooms Extractor items', [$this->items]);
    }

    protected function roomCombinationsModifier()
    {
        $roomCombinations = $this->extractCombinations();
        if (empty($roomCombinations)) {
            return $this->addError('No available combination');
        }
        foreach ($roomCombinations as $rawCombinations) {
            $rawPrice = $roomsFare = [];
            foreach ($rawCombinations as $groupIndex => $roomIndex) {
                $roomsFare[] = $this->getRoomPrice($roomIndex, $groupIndex);
                $rawPrice[] = $this->roomsPriceList[$roomIndex]['rawPrice'];
            }
            $combinationsInfo[] = [
                'roomIndexes' => $rawCombinations,
                'roomsFare' => $roomsFare,
                'payable' => array_sum($roomsFare),
                'rawPrice' => $rawPrice
            ];
        }
        return ['roomCombinations' => $roomCombinations, 'combinationsInfo' => $combinationsInfo];
    }

    protected function priceModifier($combinationsPrice, $conditionds)
    {
        ///? quantum supplier doesm't use this
        return $this->calculatePriceforAvailabilities([
            'priceInfo' => $combinationsPrice,
            'conditions' => $conditionds
        ]);
    }

    protected function mapper()
    {
        $service = $this->object->service_info;
        $service['rooms'] =  $this->items['roomDetail'];
        $service['roomsFilters'] =  $this->items['filters'];
        $this->object->service_info = $service;

        $price = $this->object->price_info;
        $price['combinations'] =  $this->sortCombinations($this->items['combinationsPrice']);
        $this->object->price_info = $price;

        $seller = $this->object->seller_info;
        $seller['afters']['availableRooms'] = true;
        $seller['combinations'] =  $this->items['combinationsInfo'];
        $this->object->seller_info = $seller;

        $this->object->update();
    }

    protected function sortCombinations($combinations)
    {
        $combs = [];
        foreach ($combinations as $combination) {
            $key = $combination['marketplacePrice']['payable'];
            while (!empty($combs[$key])) {
                $key++;
            }
            $combs[$key] = $combination;
        }

        ksort($combs);
        return array_values($combs);
    }

    protected function createFilters()
    {
        $allInclusions = $allNames = [];
        $rooms = collect($this->items['roomDetail']);
        for ($i = 0; $i < $this->getQueryParams()['rooms_no']; $i++) {
            $inclusions = $names = [];
            $roomIds = array_column($this->items['combinationsInfo'], $i);
            foreach ($roomIds ?? [] as $roomId) {
                $room = $rooms->where('index', $roomId)->first();
                $roomInclusions = empty($room['inclusion']) ? [] : explode(',', $room['inclusion']) ?? null;
                $inclusions = array_merge($inclusions, $roomInclusions);
                $names[] = $room['name'];
            }
            $allInclusions[$i] = array_values(array_unique($inclusions));
            $allNames[$i] = array_values(array_unique($names));
        }

        return [
            'name' => $allNames,
            'inclusions' => $allInclusions
        ];
    }

    protected function getQueryParams()
    {
        return $this->object->service_info['query_params'];
    }

    protected function getRoomPrice($roomIndex)
    {
        return $this->roomsPriceList[$roomIndex]['payable'];
    }
}

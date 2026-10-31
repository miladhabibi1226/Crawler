<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Hotel;

use App\TravelServices\Logics\Availabilities as LogicsAvailabilities;

trait AvailableRooms
{
    use LogicsAvailabilities;
    protected function getContent()
    {
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Quantum/HotelAvailableRooms.json'));
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $this->latestSession = 'fromFile';
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = [
            'referenceId' => $this->object->seller_info['bufferReferenceId'],
            'type' => "hotel",
            'action' => "availableRooms"
        ];
        // lugInfo('Request Data', [$depends]);
        $this->call = [
            'api' => 'v0/afterSearch',
            'host' => $auth['url'],
            'fields' => $depends,
            'apiMethod' => 'Available Rooms'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $rawResult = $this->rawResult['decoded']['data'];
        $price = $this->priceModifier($rawResult['combinations']);
        $serviceInfo = $this->hotelModifier($rawResult['roomsInfo']);
        $serviceInfo['roomsFilters'] = $this->createFilters($serviceInfo, $price['sellerInfo']);

        $this->items = [
            'serviceInfo' => $serviceInfo,
            'priceInfo' => $price['priceInfo'],
            'sellerInfo' => $price['sellerInfo']
        ];

        // lugInfo('Quantum Hotel AvailableRooms extractor item', ['items' => $this->items]);
    }

    protected function hotelModifier($rooms)
    {
        $serviceInfo = $this->object->service_info;
        $roomsInfo = [];

        foreach ($rooms as $room) {
            $roomsInfo[] = [
                'name' => $room['name'],
                'index' => $room['index'],
                "promtion" => $room['promtion'],
                "amenities" => $room['amenities'],
                "inclusion" => $room['inclusion'],
                "suppliments" => $room['suppliments'],
                "cancelationPolicies" => $room['cancelationPolicies'],
            ];
        }
        $serviceInfo['rooms'] = $roomsInfo;
        return $serviceInfo;
    }

    protected function mapper()
    {
        $this->object->service_info = $this->items['serviceInfo'];
        $this->object->price_info = $this->items['priceInfo'];
        $seller = $this->object->seller_info;
        $seller['afters']['availableRooms'] = true;
        $seller['combinations'] = $this->items['sellerInfo']['combinations'];
        $this->object->seller_info = $seller;
        $this->object->update();
    }

    protected function priceModifier($combinationsPrice)
    {
        $sellerInfo = $this->object->seller_info;
        $priceInfo = $this->object->price_info;

        $combinations = [];
        foreach ($combinationsPrice as $key => $combination) {
            $combinations[$key] = $combination['roomIndexes'];
            $roomsFare = [];
            $priceForEachRoom = $combination['priceDetails']['payable'] / count($combination['roomIndexes']);
            for ($i = 1; $i <= count($combination['roomIndexes']); $i++) {
                $roomsFare[] = $priceForEachRoom;
            }
            $combinationsInfo[] = [
                'roomIndexes' => $combination['roomIndexes'],
                'roomsFare' => $roomsFare,
                'payable' => $combination['priceDetails']['payable'],
                'rawPrice' => $combination
            ];
        }

        $prices = $this->calculatePriceforAvailabilities([
            'priceInfo' => $combinationsInfo,
            'conditions' => $this->object->price_info['exceptionInfo']
        ]);

        $sellerInfo['combinations'] = $combinations;
        $priceInfo['combinations'] = $prices;
        return ['priceInfo' => $priceInfo, 'sellerInfo' => $sellerInfo];
    }

    protected function createFilters($hotel, $sellerInfo)
    {
        $allInclusions = $allNames = [];
        $rooms = collect($hotel['rooms']);
        for ($i = 0; $i < $this->queryParams['rooms_no']; $i++) {
            $inclusions = $names = [];
            $roomIds = array_column($sellerInfo['combinations'], $i);
            foreach ($roomIds as $roomId) {
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
}

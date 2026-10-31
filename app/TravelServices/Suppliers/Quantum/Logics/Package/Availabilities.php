<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Package;

use App\TravelServices\Suppliers\Quantum\Logics\Availabilities as QuantumAvailabilities;
trait Availabilities
{
    use QuantumAvailabilities;
    protected function availabilitiesModifier($rawResult)
    {
        $availabilities = $rawResult['availabilities'];
        $items = ['serviceInfo' => [], 'priceInfo' => [], 'sellerInfo' => []];
        foreach ($availabilities as $key => $availability) {
            $roomPricing = [];
            foreach ($availability['rooms'] as $roomType => $room) {
                $roomPricing[$roomType] =  $room['pricing']['payable'];
                unset($availability['rooms'][$roomType]['pricing']);
            }
            ///> key is importsnt in time of booking
            $items['sellerInfo'][$key] = $key; ///> the value isn't important here. only the key will be used in marketplace
            $items['serviceInfo'][$key] = $availability;
            $items['priceInfo'][$key] = [
                'discount' => $availability['discount'],
                'rooms' => $roomPricing,
                'conditions' => []
            ];
        }

        $items = $this->extraDatafromApiOut($items);

        $availabilitiesPrices = $this->priceModifier($items['priceInfo']);

        $serviceInfo = $this->object->service_info;
        $serviceInfo['availabilities'] = $items['serviceInfo'];

        $priceInfo = $this->object->price_info;
        $priceInfo['availabilities'] = $availabilitiesPrices;

        $sellerInfo = $this->object->seller_info;
        $sellerInfo['availabilities'] = $items['sellerInfo'];
        $serviceInfo = $this->includeMinPriceInServiceInfo($serviceInfo, $priceInfo);

        return ['serviceInfo' => $serviceInfo, 'priceInfo' => $priceInfo, 'sellerInfo' => $sellerInfo];
    }

    protected function mapper()
    {
        $this->object->service_info = $this->items['serviceInfo'];
        $seller = $this->items['sellerInfo'];
        $seller['afters']['availabilities'] = true;
        $this->object->seller_info = $seller;
        $this->object->price_info = $this->items['priceInfo'];
        $this->object->update();
    }

    protected function includeMinPriceInServiceInfo($serviceInfo, $priceInfo)
    {
        foreach ($serviceInfo['availabilities'] as $index => $availability) {
            $minPrice = 0;
            foreach ($priceInfo['availabilities'][$index]['rooms'] as $roomPriceInfo) {
                $roomAdultPrice = $roomPriceInfo['marketplacePrice']['payable']['adult'];
                $minPrice = empty($minPrice) ? $roomAdultPrice : ($minPrice < $roomAdultPrice ? $minPrice : $roomAdultPrice);
            }
            $serviceInfo['availabilities'][$index]['minPrice'] = $minPrice;
        }
        return $serviceInfo;
    }
}

<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Tour;

use App\TravelServices\Suppliers\Quantum\Logics\Availabilities as QuantumAvailabilities;

trait Availabilities
{
    use QuantumAvailabilities;

    protected function availabilitiesModifier($rawResult)
    {
        $availabilities = $rawResult['availabilities'];
        $availabilitiesPrices = [];
        $availabilitiesInfo = [];
        foreach ($availabilities as $key => $availability) {
            $id = $availability['id'];
            $pricing = $availability['pricing'];
            $availabilitiesInfo[] = [
                "id" => $id,
                "to" => $availability['to'],
                "from" => $availability['from'],
                "old_price" => $pricing['b2c']['adult'],
                "adult_price" => $pricing['payable']['adult'],
                "child_price" => $pricing['payable']['child'],
                "infant_price" => $pricing['payable']['infant'],
                'reference_id' => $availability['referenceId'],
                "remaining_capacity" => $availability['remaining_capacity']
            ];
        }

        $availabilitiesPrices = $this->priceModifier($availabilitiesInfo);

        $serviceInfo = $this->object->service_info;
        $serviceInfo['availabilities'] = $availabilitiesInfo;

        $priceInfo = $this->object->price_info;
        $priceInfo['availabilities'] = $availabilitiesPrices;

        return ['serviceInfo' => $serviceInfo, 'priceInfo' => $availabilitiesPrices];
    }

    protected function mapper()
    {
        $this->object->service_info = $this->items['serviceInfo'];
        $seller = $this->object->seller_info;
        $seller['afters']['availabilities'] = true;
        $this->object->seller_info = $seller;
        $price = $this->object->price_info;
        $price['availabilities'] = $this->items['priceInfo'];
        $this->object->price_info = $price;
        $this->object->update();
    }
}

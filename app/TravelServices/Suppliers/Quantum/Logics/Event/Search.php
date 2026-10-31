<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Event;

use App\TravelServices\Logics\Search as MainSearch;
use App\TravelServices\Suppliers\Quantum\Logics\Search as QuantumSearch;

trait Search
{
    use QuantumSearch, MainSearch;
    protected $resultSample = 'Samples/Quantum/EventSearch.json';

    protected function getFields()
    {
        if (!empty($this->queryParams['destination_city']['id'])) {
            $depends['city'] = $this->queryParams['destination_city']['id'];
        }
        if (!empty($this->queryParams['startDate'])) {
            $depends['startDate'] = $this->queryParams['startDate'];
            $depends['endDate'] = $this->queryParams['endDate'];
        }
        return $depends ?? [];
    }

    protected function priceModifier($priceInfo, $conditions = [])
    {
        return [
            'priceInfo' => [
                'oldPrice' => $priceInfo['oldPrice'],
                'priceStartsFrom' => $priceInfo['payable'],
                'totalPrice' => $priceInfo['payable']
            ],
            'conditions' => $conditions,
            'rawPrices' => $priceInfo
        ];
    }

    protected function getSellerInfo($item)
    {
        return [
            'onholdable' => $item['onholdable'],
            'passengersForm' => $item['passengersForm'],
            'afters' => [
                'availabilities' => false,
            ],
            'bufferReferenceId' => $item['referenceId']
        ];
    }

    protected function getBufferData($item, $onholdable, $now)
    {
        return [
            'reference_id' => createReferenceId(),
            'search_query_id' => $this->object->id,
            'seller_id' => $this->seller->id,
            'seller_info' => json_encode($item['sellerInfo']),
            'service_info' => json_encode($item['serviceInfo']),
            'price_info' => json_encode($item['priceInfo']),
            'currency_id' => $this->queryParams['marketplace_currency']['id'],
            'total_b2b' => 0, ///> plugin will fill this
            'total_b2c' => 0, ///> plugin will fill this
            'title' => $item['serviceInfo']['title'],
            'travel_style' =>  implode(',', array_keys($item['serviceInfo']['travelStyle'])),
            'landmark_id' => $item['serviceInfo']['landmark']['id'],
            'is_group' => empty($item['serviceInfo']['group_size']) ? 0 : 1,
            'is_onholdable' => $onholdable && $item['onholdable'],
            'created_at' => $now,
        ];
    }
}

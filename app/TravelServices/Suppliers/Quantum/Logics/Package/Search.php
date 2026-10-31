<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Package;

use App\TravelServices\Logics\Search as MainSearch;
use App\TravelServices\Suppliers\Quantum\Logics\Search as QuantumSearch;

trait Search
{
    use QuantumSearch, MainSearch;
    protected $resultSample = 'Samples/Quantum/PackageSearch.json';

    protected function getFields()
    {
        if (!empty($this->queryParams['destination_city']['id'])) {
            $depends['city'] = $this->queryParams['destination_city']['id'];
        }
        if (!empty($this->queryParams['departure_from']['id'])) {
            $depends['departureFrom'] = $this->queryParams['departure_from']['id'];
        }
        if (!empty($this->queryParams['startDate'])) {
            $depends['startDate'] = $this->queryParams['startDate'];
            $depends['endDate'] = $this->queryParams['endDate'];
        }
        return $depends ?? [];
    }

    protected function getSellerInfo($item)
    {
        return [
            'afters' => [
                'availabilities' => false,
            ],
            'passengersForm' => $item['passengersForm'],
            'bufferReferenceId' => $item['referenceId']
        ];
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
            'rawPrices' => $priceInfo,
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
            'title' => reset($item['serviceInfo']['package']['title']),
            'travel_style' => implode(',', array_keys($item['serviceInfo']['package']['travelStyle'])),
            'lodging_level' => $item['serviceInfo']['package']['lodgingLevel'],
            'is_group' => empty($item['serviceInfo']['package']['group_size']) ? 0 : 1,
            'is_onholdable' => $onholdable && $item['onholdable'],
            'created_at' => $now,
            'departure_from' => $item['serviceInfo']['package']['departureFrom']['id'] ?? null,
        ];
    }
}

<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Visa;

use App\TravelServices\Logics\Search as MainSearch;
use App\TravelServices\Suppliers\Quantum\Logics\Search as QuantumSearch;

trait Search
{
    use QuantumSearch, MainSearch;
    protected $resultSample = 'Samples/Quantum/VisaSearch.json';

    public function getFields()
    {
        if (!empty($this->queryParams['country']['id'])) {
            $depends['country'] = $this->queryParams['country']['id'] ?? null;
        }
        if (!empty($this->queryParams['passport_country']['id'])) {
            $depends['passportCountry'] = $this->queryParams['passport_country']['id'] ?? null;
        }
        return $depends ?? [];
    }

    protected function getSellerInfo($item)
    {
        return [
            'passengersForm' => $item['passengersForm'],
            'bufferReferenceId' => $item['referenceId']
        ];
    }

    protected function priceModifier($totalPrice, $conditions = [])
    {
        return [
            'priceInfo' => ['totalPrice' => $totalPrice['payable']],
            'conditions' => $conditions,
            'rawPrices' => $totalPrice
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
            'type' => json_encode($item['serviceInfo']['visa']['type']),
            'is_onholdable' => $onholdable && $item['onholdable'],
            'created_at' => $now
        ];
    }
}

<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight\New;

use App\TravelServices\Helpers\QueryParams;
use App\TravelServices\Logics\Flight\TargetSearch as CommonTargetSearch;

trait TargetSearch
{
    use CommonTargetSearch, CustomSearch, QueryParams {
        CommonTargetSearch::priceInfo insteadof CustomSearch;
        CommonTargetSearch::chekCapacity insteadof CustomSearch;
    }

    protected function collectOriginDestinationInformations()
    {
        //> re-writing this here so we only get the price and baggages for the one item we need
        foreach ($this->finalRecomendations as $finalRecomendation) {
            $flightNumbers = [];
            foreach ($finalRecomendation['legs'] as $segments) {
                foreach ($segments as $segment) {
                    $flightNumbers[] = $segment['flightNumber'];
                }
            }
            $key = implode(' / ', $flightNumbers);
            if ($key != ($this->object->seller_info['flightNumbers'] ?? $key)) continue;
            $this->OriginDestinationInformations[$key] = $finalRecomendation;
        }
    }

    protected function priceInfo($price)
    {
        $data = $this->calculatePriceforEachItem([['price_info' => json_encode($price)]]);
        $mainPriceInfo = json_decode($data[0]['price_info'] ?? '', 'true');
        if (!empty($this->additionalFees())) {
            $mainPriceInfo['rawPrices']['additionalFees'] = $this->additionalFees();
        }
        return $mainPriceInfo;
    }
}

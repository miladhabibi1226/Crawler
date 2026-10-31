<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight;

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
        $this->translatedQueryParams = $this->translateQueryParams(true);
        if ($this->translatedQueryParams['tripType'] === 'multiDestination') {
            $multiOriginDestinationInformations = $this->searchRawResult['decoded'][0]['OTA_AirAvailRS']['OriginDestinationInformation'];
            lugInfo('$multiOriginDestinationInformations', [$multiOriginDestinationInformations]);
            foreach ($multiOriginDestinationInformations as $multiOriginDestinationInformation) {
                $OriginDestinationInformations['OriginDestinationOptions']['OriginDestinationOption'][] =  $multiOriginDestinationInformation['OriginDestinationOptions']['OriginDestinationOption'] ?? [];
            }
        } else {
            $OriginDestinationInformations = $this->searchRawResult['decoded'][0]['OTA_AirAvailRS']['OriginDestinationInformation'];
        }
        //> re-writing this here so we only get the price and baggages for the one item we need
        $this->repelSingleMode($OriginDestinationInformations);
        foreach ($OriginDestinationInformations as $index => $OriginDestinationInformation) {
            $segments = $OriginDestinationInformation['OriginDestinationOptions']['OriginDestinationOption'];
            $this->repelSingleMode($segments);
            $flightNumbers = array_column(array_column(array_column($segments, 'FlightSegment'), '@attributes'), 'FlightNumber');
            $flightNumbers = implode('_', $flightNumbers);
            if ($flightNumbers != ($this->object->seller_info['flightNumbers'] ?? $flightNumbers)) continue;
            $this->OriginDestinationInformations[$flightNumbers]['OriginDestinationOptions']['OriginDestinationOption'] = $segments;
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

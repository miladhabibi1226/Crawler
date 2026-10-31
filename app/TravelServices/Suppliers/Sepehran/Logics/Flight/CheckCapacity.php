<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;

trait CheckCapacity
{
    use TargetSearch, BeforeBook {
        TargetSearch::canBeDone as TargetSearchCanBeDone;
        TargetSearch::getContent insteadof BeforeBook;
        TargetSearch::decodeRawResult insteadof BeforeBook;
        TargetSearch::mapper insteadof BeforeBook;
        BeforeBook::failed insteadof TargetSearch;
    }

    protected $newPrices = [];
    protected $oldPrices = [];

    protected function canBeDone()
    {
        return $this->TargetSearchCanBeDone();
    }

    protected function done()
    {
        return ['status' => true];
    }

    public function requestGenerator()
    {
        $this->multi = true;
        $auth = $this->seller->api->configs;
        $this->translatedQueryParams = $this->translateQueryParams(true);
        foreach ($this->translatedQueryParams['legs'] as $key => $leg) {
            $this->translatedQueryParams['legs'][$key]['origin']['abb'] = $this->getServiceInfo()['legs'][$key]['info']['departure']['airport']['abb'];
            $this->translatedQueryParams['legs'][$key]['destination']['abb'] = $this->getServiceInfo()['legs'][$key]['info']['arrival']['airport']['abb'];
            $this->prepareCalls($auth, $this->translatedQueryParams);
        }
        return $this->action();
    }

    protected function itinerary($rawResults)
    {
        foreach ($rawResults as $itinerary) {
            $item = [];
            $this->prepareInfo($itinerary);
            if (!($item['service'] = $this->legModifier($this->originDestinationOption($itinerary), $this->validatingAirlineCode($itinerary)))) continue;
            if (!$this->filterRBD($item['service'])) continue;
            if (!$this->filterFlightNumbers($item['service'])) continue;
            if (!$this->filterWeight($item['service'])) continue;
            if (!$this->filterAirlines($item['service'])) continue;
            if (!($item['price'] = $this->priceModifier($this->supplierRawPricing($itinerary), $this->getPricingConditions($item['service'])))) continue;
            $item['sellerInfo'] = $this->sellerInfoModifier($itinerary);
            $this->newPrices[] = $priceNew = $item['price']['priceInfo']['payable'];
            $this->oldPrices[] = $priceOld =  $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'];
            if ($this->checkPrice($priceNew, $priceOld) && $this->capacityCheck($item['service'])) {
                $selected = $item;
            }
        }
        if (empty($selected)) {
            lugError('Flight not found');
            $this->addError(trans('messages.unavailable'));
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return [];
        }
        return [$selected];
    }

    protected function filterFlightNumbers($serviceInfo)
    {
        foreach ($serviceInfo['legs'] as $key => $legNew) {
            $legOld = $this->getServiceInfo()['legs'][$key];
            if (($legNew['info']['flight_number'] ?? false) != ($legOld['info']['flight_number'] ?? false)) return false;
        }
        return true;
    }

    protected function filterRBD($serviceInfo)
    {
        foreach ($serviceInfo['legs'] as $key => $legNew) {
            $legOld = $this->getServiceInfo()['legs'][$key];
            if (($legNew['info']['resBookDesigCode'] ?? false) != ($legOld['info']['resBookDesigCode'] ?? false)) return false;
        }
        return true;
    }

    protected function filterWeight($serviceInfo)
    {
        foreach ($serviceInfo['legs'] as $key => $legNew) {
            $legOld = $this->getServiceInfo()['legs'][$key];
            if (($legNew['segments'][0]['luggage'] ?? false) != ($legOld['segments'][0]['luggage'] ?? false)) return false;
        }
        return true;
    }

    protected function filterAirlines($serviceInfo)
    {
        foreach ($serviceInfo['legs'] as $key => $legNew) {
            $legOld = $this->getServiceInfo()['legs'][$key];
            if (($legNew['info']['airline']['id'] ?? false) != ($legOld['info']['airline']['id'] ?? false)) return false;
        }
        return true;
    }

    protected function capacityCheck($serviceInfo)
    {
        $totalPassengers = $this->queryParams['adults'] + $this->queryParams['children'];
        if (!empty($serviceInfo['legs'])) {
            foreach ($serviceInfo['legs'] as $leg) {
                if ($leg['info']['capacity'] == '0' || $leg['info']['capacity'] <  $totalPassengers) {
                    lugError('capacity is full', ['legs-info' => $serviceInfo['legs'] ?? [], 'request-info' => $this->queryParams ?? [], 'totalPassengers' => $totalPassengers ?? []]);
                    return false;
                }
            }
        }
        return true;
    }

    protected function checkPrice($priceNew, $priceOld)
    {
        if (!(round($priceNew, 5) == round($priceOld, 5))) {
            lugError('price has changed ', ['oldPrices' => $this->oldPrices ?? [], 'newPrices' => $this->newPrices ?? [], 'priceNew' => $priceNew ?? [], 'priceOld' => $priceOld ?? [], ' $this->rawResult' => $this->rawResult ?? []]);
            return false;
        }
        return true;
    }
}

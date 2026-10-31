<?php

namespace App\TravelServices\Suppliers\CityNet\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;
use App\TravelServices\Suppliers\CityNet\Logics\Flight\TargetSearch;

trait Revalidate
{
    use TargetSearch, BeforeBook {
        TargetSearch::canBeDone as TargetSearchCanBeDone;
        TargetSearch::getContent insteadof BeforeBook;
        TargetSearch::decodeRawResult insteadof BeforeBook;
        TargetSearch::mapper insteadof BeforeBook;
        BeforeBook::failed insteadof TargetSearch;
    }

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
        $auth = $this->seller->api->configs;
        $this->translatedQueryParams = $this->translateQueryParams();
        foreach ($this->translatedQueryParams['legs'] as $key => $leg) {
            $this->translatedQueryParams['legs'][$key]['origin']['abb'] = $this->getServiceInfo()['legs'][$key]['info']['departure']['airport']['abb'];
            $this->translatedQueryParams['legs'][$key]['destination']['abb'] = $this->getServiceInfo()['legs'][$key]['info']['arrival']['airport']['abb'];
        }
        $depends = $auth + $this->translatedQueryParams;
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($auth['url']) . '/flights/search',
            'timeout' => 30,
            'apiMethod' => 'Search'
        ];
        return $this->action();
    }

    protected function itinerary($rawResults)
    {
        foreach ($rawResults as $itinerary) {
            $item = [];
            if (!($item['service'] = $this->legModifier($this->originDestinationOption($itinerary), $this->validatingAirlineCode($itinerary)))) continue;
            if (!$this->filterRBD($item['service'])) continue;
            if (!$this->filterFlightNumbers($item['service'])) continue;
            if (!$this->filterWeight($item['service'])) continue;
            if (!$this->filterAirlines($item['service'])) continue;
            if (!($item['price'] = $this->priceModifier($this->supplierRawPricing($itinerary), $this->getPricingConditions($item['service'])))) continue;
            $item['sellerInfo'] = $this->sellerInfoModifier($itinerary);
            $newPrices[] = $priceNew = $item['price']['priceInfo']['payable'];
            $oldPrices[] = $priceOld =  $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'];
            if (round($priceNew, 5) == round($priceOld, 5)) {
                $selected = $item;
            }
        }
        if (empty($selected)) {
            lugError('Flight not found', [$oldPrices ?? [], $newPrices ?? [], $this->rawResult]);
            $this->addError('The item is no longer available, or the price has changed');
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
}

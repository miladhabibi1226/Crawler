<?php

namespace App\TravelServices\Suppliers\Kplus\Logics\Flight;

use App\TravelServices\Logics\BeforeBook as CommonBeforeBook;

trait BeforeBook
{
    ////> Revalidate and GetBrandedFares use this trait
    use CommonBeforeBook, Recomendations;
    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs + $this->object->seller_info;
        lugInfo("$this->method data", [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'method' => 'POST',
            'url' =>  "{$depends['url']}/Air.svc/Rest/Json/$this->method",
            'timeout' => 20,
            'apiMethod' => $this->method
        ];
        return $this->action();
    }

    public function extractor()
    {
        $this->items = [];
        $rawResults = $this->rawResult['decoded']['Result']['SearchResults'] ?? [];
        if (empty($rawResults)) {
            lugError('no Results', [$this->rawResult]);
            $this->addError('no AirItineraryPricingInfo');
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return false;
        }
        if (!$this->findItem($this->getRecommendations($rawResults))) {
            $this->addError('could not find the item');
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return false;
        }
        if (!$this->checkForPriceChange()) {
            lugError('Price Changed', [$this->items]);
            $this->addError('Price Changed');
            $this->addErrorToBook(trans('messages.priceChanged'));
            $this->failBook();
            return false;
        }
        lugInfo("Kplus $this->method extractor Items", ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->seller_info = array_merge($this->object->seller_info,  ["{$this->method}Keys" => $this->items['flight']['keys']]);
        $this->object->update();
    }

    protected function findItem($recomendations)
    {
        $serviceInfo = $this->getServiceInfo();
        foreach ($recomendations as $item) {
            foreach ($item['legs'] as $legIndex => $leg) {
                foreach ($leg['Segments'] as $segIndex => $segment) {
                    if ($segment['DepartureDate'] != $serviceInfo['legs'][$legIndex]['segments'][$segIndex]['departure']['raw_time']) continue 3;
                    if ($segment['ArrivalDate'] != $serviceInfo['legs'][$legIndex]['segments'][$segIndex]['arrival']['raw_time']) continue 3;
                    if ($segment['FlightNo'] != $serviceInfo['legs'][$legIndex]['segments'][$segIndex]['flight_number']) continue 3;
                    $cabins = ['All' => 0, 'Promo' => 1, 'First' => 2, 'Business' => 3, 'Economy' => 4, 'Premium First' => 5, 'Premium Economy' => 6, 'Premium' => 7, 'Premium Business' => 8];
                    if ($segment['fareInfo']['CabinType'] != $cabins[$serviceInfo['legs'][$legIndex]['segments'][$segIndex]['cabin']]) continue 3;
                }
            }
            $items[] = $item;
        }
        return $this->items['flights'] = $items ?? [];
    }

    protected function checkForPriceChange()
    {
        $this->items['oldPrice'] = round($this->object->price_info['sellerPrice']['rawPrice']['TotalPrice']['TotalAmount'], 5);
        foreach ($this->items['flights'] as $key => $flight) {
            $this->items['newPrice'][$key] = $newPrice = round($flight['priceInfo']['TotalPrice']['TotalAmount'], 5);
            if ($newPrice == $this->items['oldPrice']) {
                return $this->items['flight'] = $flight;
            }
        }
        return false;
    }

    protected function getFields($depends)
    {
        return [
            "request" => [
                "Air" => ["FareAlternativeLegKeys" => $depends[$this->keys]],
                "Token" => ["TokenCode" => $this->latestSession]
            ]
        ];
    }
}

<?php

namespace App\TravelServices\Logics\Flight;

trait CustomSearch
{
    ////> Local supplier (targetSearch) does not use this class at all
    protected function itinerary($rawResults)
    {
        foreach ($rawResults as $itinerary) {
            $item = [];
            $this->prepareInfo($itinerary);
            if (!($item['service'] = $this->legModifier($this->originDestinationOption($itinerary), $this->validatingAirlineCode($itinerary)))) continue;

            if (!$this->filterRBD($item['service'])) {
                continue;
            }
            if (!$this->filterFlightNumbers($item['service'])) {
                continue;
            }
            if (!$this->filterWeight($item['service'])) {
                continue;
            }
            if (!$this->filterAirlines($item['service'])) {
                continue;
            }
            if (!($item['price'] = $this->priceModifier($this->supplierRawPricing($itinerary), $this->getPricingConditions($item['service'])))) continue;
            $item['sellerInfo'] = $this->sellerInfoModifier($itinerary);
            $price = $item['price']['priceInfo']['payable'];
            if (empty($selected) || $price < $selected['price']['priceInfo']['payable']) {
                $selected = $item;
            }
        }
        if (empty($selected)) {
            // lugWarning('No item sellected', []);
            $this->expireSupplierCache();
            return [];
        }
        //chech capacity
        if (!$this->chekCapacity($selected)){
            $this->expireSupplierCache();
            return [];
        }
        $selected['price'] = $this->priceInfo($selected['price']);
        if ($selected['price']['marketplacePrice']['payable'] != $this->object->buffer->price_info['marketplacePrice']['payable']) {
            ///> if price changes, the result is acceptable but we want to clear the cache so that if someone searched it again, it shows the correct price
            lugWarning('price changed', ['old' => $this->object->buffer->price_info['marketplacePrice']['payable'], 'new' => $selected['price']['marketplacePrice']['payable']]);
            $this->expireSupplierCache();
        }
        lugInfo('sellectedItem', ['item' => $selected]);
        return [$selected];
    }
    protected function priceInfo($price)
    {
        return ['sellerPrice' => $price['priceInfo'], 'nodePrice' => $this->exchangePriceList($price['priceInfo'], $this->seller->currency->toArray(), $this->queryParams['marketplace_currency'])];
    }

    protected function filterFlightNumbers($serviceInfo)
    {
        return ($serviceInfo['legs'][0]['info']['flight_number'] ?? false) == ($this->queryParams['departureFlightInfo']['flightNumber'] ?? ($serviceInfo['legs'][0]['info']['flight_number'] ?? false))
            && ($serviceInfo['legs'][1]['info']['flight_number'] ?? false) == ($this->queryParams['returnFlightInfo']['flightNumber'] ?? ($serviceInfo['legs'][1]['info']['flight_number'] ?? false));
    }

    protected function filterRBD($serviceInfo)
    {
        return strtoupper($serviceInfo['legs'][0]['info']['resBookDesigCode'] ?? '') == strtoupper($this->queryParams['departureFlightInfo']['class'] ?? ($serviceInfo['legs'][0]['info']['resBookDesigCode'] ?? ''))
            && strtoupper($serviceInfo['legs'][1]['info']['resBookDesigCode'] ?? '') == strtoupper($this->queryParams['returnFlightInfo']['class'] ?? ($serviceInfo['legs'][1]['info']['resBookDesigCode'] ?? ''));
    }

    protected function filterWeight($serviceInfo) ///> re written for sabre / quantum (targetSearch)
    {
        return strtoupper($serviceInfo['legs'][0]['segments'][0]['luggage'] ?? '') == strtoupper(($this->queryParams['departureFlightInfo']['luggage'] ?? ($serviceInfo['legs'][0]['segments'][0]['luggage'] ?? '')))
            && strtoupper($serviceInfo['legs'][1]['segments'][0]['luggage'] ?? '') == strtoupper(($this->queryParams['returnFlightInfo']['luggage'] ?? ($serviceInfo['legs'][1]['segments'][0]['luggage'] ?? '')));
    }

    protected function filterAirlines($serviceInfo)
    {
        return strtoupper($serviceInfo['legs'][0]['info']['airline']['id'] ?? '') == strtoupper(($this->queryParams['departureFlightInfo']['airline'] ?? ($serviceInfo['legs'][0]['info']['airline']['id'] ?? '')))
            && strtoupper($serviceInfo['legs'][1]['info']['airline']['id'] ?? '') == strtoupper(($this->queryParams['returnFlightInfo']['airline'] ?? ($serviceInfo['legs'][1]['info']['airline']['id'] ?? '')));
    }

    protected function done()
    {
        return [
            'status' => true,
            'items' => $this->items,
            'queryParams' => $this->queryParams
        ];
    }

    protected function failed($error = null)
    {
        return  ['status' => false];
    }

    protected function chekCapacity($flight) {}

    protected function exchangePriceList($pricing, array $sourseCurrency, object $destinationCurrency)
    {
        if ($sourseCurrency['id'] == $destinationCurrency->id) {
            return $pricing;
        }
        foreach ($pricing as $key => $data) {
            if (in_array($key, ['currency'])) {
                $pricing[$key] = $destinationCurrency;
            } elseif (in_array($key, ['passengersCount'])) {
                $pricing[$key] = $data;
            } elseif (is_numeric($data)) {
                $pricing[$key] = exchange($data, $sourseCurrency['id'], $destinationCurrency->id);
            } elseif (is_bool($data) || is_string($data)) {
                $pricing[$key] = $data;
            } elseif (is_array($data)) {
                $pricing[$key] = $this->exchangePriceList($data, $sourseCurrency, $destinationCurrency);
            }
        }
        return $pricing;
    }
}

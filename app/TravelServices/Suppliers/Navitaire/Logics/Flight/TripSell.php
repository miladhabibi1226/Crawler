<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;

trait TripSell
{
    use BeforeBook;
    protected $responseSample = 'Samples/Navitaire/TripSell.json';
    public function sessionHandling()
    {
        return $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth;
        lugInfo('TripSell data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'timeout' => 20,
            'url' => $this->cleanUrlSlash($auth['url']) . $this->getTripSellUrl(),
            'apiMethod' => 'TripSell'
        ];
        return $this->action();
    }

    protected function getTripSellUrl()
    {
        return '/api/jz/v1/TripSell';
    }

    protected function getRawResultUrl()
    {
        return $this->rawResult['decoded']['data']['Trip']['tripAddv4'] ?? [];
    }

    public function extractor()
    {
        $Result = $this->getRawResultUrl();
        $this->exchangeData = $this->object->price_info['sellerPrice']['rawPrice']['exchangeData'] ?? [];
        if (empty($Result['breakdown'])) {
            lugError('empty breakdown', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            $this->addErrorToBook(trans('messages.unavailable'));
            return $this->addError('empty Result');
        }
        $this->checkForPriceChange($Result['breakdown']['balanceDue']);
        $this->items['sellerInfo']['bookingSession'] = $this->latestSession;
        $this->getPassengersKeys($Result);
        lugInfo('Navitaire Flight TripSell extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->seller_info = array_merge($this->object->seller_info, $this->items['sellerInfo']);
        $this->object->update();
    }

    protected function getPassengersKeys($Result)
    {
        foreach ($Result['passengers'] as $passenger) {
            $passengerType = ['ADT' => 'adults', 'CHD' => 'children', 'INF' => 'infants'][$passenger['value']['passengerTypeCode']];
            $this->items['sellerInfo']['passengersKeys'][$passengerType][] = $passenger['key'];
        }
    }

    protected function getFields($depends)
    {
        foreach ($depends['bookingData'] as $data) {
            $keys[] = [
                "journeyKey" => $data['journeyKey'],
                "fareAvailabilityKey" => $data['fareAvailabilityKey'],
                "standbyPriorityCode" => '',
                "inventoryControl" => ''
            ];
        }
        $fields = [
            "keys" => $keys,
            "passengers" => ["types" => [["type" => "ADT", "count" => $this->queryParams['adults']]]],
            "currencyCode" => $depends['resultCurrency'],
            "infantCount" => $this->queryParams['infants'],
            "promotionCode" => '',
            "sourceOrganization" => '',
        ];
        if (!empty($this->queryParams['children'])) {
            $fields['passengers']['types'][] = ["type" => "CHD", "count" => $this->queryParams['children']];
        }
        return $fields;
    }

    protected function handleSignOut()
    {
        $this->callSignOut($this->latestSession);
    }
}

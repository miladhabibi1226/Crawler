<?php

namespace App\TravelServices\Suppliers\TopTravel\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;
use Illuminate\Support\Str;
use MatinUtils\NodeTracker\Models\ServiceInfo;
use App\TravelServices\Helpers\QueryParams;

trait FareConfirm
{
    use BeforeBook, QueryParams;
    protected  $translatedQueryParams;

    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $this->translatequeryParams = $this->translateQueryParams();
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth;
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/satguruapiout/v1/flight/fareConfirm",
            'headers' => [
                'Authorization:Bearer ' . $this->latestSession,
                'Content-Type: application/json'
            ],
            'apiMethod' => 'Fare Confirm'
        ];
        return $this->action();
    }

    protected function getFields($auth)
    {
        $cabin = match ($this->queryParams['cabin']) {
            'Economy' => '1',
            'PremiumEconomy' => '2',
            'Business' => '3',
            'First' => '4',
        };
        foreach ($this->translatequeryParams['legs'] as $legIndex => $leg) {
            $legs[] = [
                "cabinClass" => $cabin,
                "destination" => $leg['destination']['abb'],
                "journeyDate" =>  $leg['departure'],
                "origin" => $leg['origin']['abb']
            ];
        }
        $data = [
            "agencyCode" => Str::before($auth['username'], ':'),
            "flightOptionKey" => $auth['flightOptionKey'],
            "flightSearchKey" => $auth['flightSearchKey'],
            "flightSearchWidgetList" => $legs,
            "noOfAdults" => $this->queryParams['adults'],
            "noOfChilds" => $this->queryParams['children'] ?? 0,
            "noOfInfants" => $this->queryParams['infants'] ?? 0,
            "tripType" =>  $this->queryParams['tripType']
        ];
        return $data;
    }


    public function extractor()
    {
        if ($this->queryParams['tripType'] === 'oneWay') {
            $result = $this->rawResult['decoded']['onwardFlightOption'];
            $totalFare = $result['totalFare'];
        } else {
            $result = $this->rawResult['decoded']['roundTripFlightOption'];
            $totalFare = $result['onwardFlightOption']['totalFare'] +  $result['returnFlightOption']['totalFare'];
        }
        $this->items = [];
        if (empty($totalFare)) {
            lugError('no priceInfo', [$this->rawResult]);
            $this->addError('no PricingInfo');
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return false;
        }
        $this->checkForPriceChange($totalFare);
        $this->object->seller_info['flightOptionKey'] = $this->rawResult['decoded']['selectedFlightOptionKey'];
        $this->object->update();
    }

    protected function checkForPriceChange($totalFare)
    {
        ///> currently we will fail the booking if the price has changed
        $oldPrice = $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'];
        if (round($totalFare, 4) !== round($oldPrice, 4)) {
            lugError('Price Change', ['Old Price' => $oldPrice, 'New Price' => $totalFare]);
            $this->addError("Price has changed");
            if ($this->objectIsBooking) {
                $this->failBook();
                $this->addErrorToBook(trans('messages.priceChanged'));
            }
        }
    }

    protected function mapper()
    {
        $sellerInfo = [];
        $sellerInfo['selectedFlightOptionKey'] = $this->rawResult['decoded']['selectedFlightOptionKey'];
        $this->object->seller_info = array_merge($this->object->seller_info,  $sellerInfo);
        $this->object->update();
    }
}

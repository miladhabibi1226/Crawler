<?php

namespace App\TravelServices\Suppliers\FlyErbil\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;
trait PriceQuote
{
    use BeforeBook;
    public function sessionHandling()
    {
        if ($this->checkSession($this->object->seller_info['session']) === false) {
            $this->addErrorToBook(trans('messages.session'));
            $this->failBook();
            return false;
        }
        return true;
    }

    protected function getContent()
    {
        // $this->fromFile =1;
        if ($this->fromFile) {
            $xml = file_get_contents(storage_path('Samples/FlyErbil/PriceQuote.xml'));
            //> next 2line: remove ota:OTA_AirLowFareSearchRS attributes and remove ota namespace bc they caused error
            $xml = preg_replace('/(<ota:OTA_AirBookRS)([^>]+)>/', '$1>', $xml);
            $xml = preg_replace('/ota:/m', '', $xml);
            $this->rawResult['body'] = $xml;
            $xml = new \SimpleXMLElement($xml);
            $this->rawResult['decoded'] = json_decode(json_encode($xml), true);
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addErrorToBook("Supplier connection error");
            $this->failBook();
            return false;
        }

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }

        //> next 2line: remove ota:OTA_AirLowFareSearchRS attributes and remove ota namespace bc they caused error
        $this->rawResult['body'] = preg_replace('/(<ota:OTA_AirPriceRS)([^>]+)>/', '$1>', $this->rawResult['body']);
        $this->rawResult['body'] = preg_replace('/ota:/m', '', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml), true);
    }

    public function requestGenerator()
    {
        $this->latestSession = json_decode($this->supplierSession->extras, true);
        $auth = $this->seller->api->configs;
        $depends = $auth + ['passengers' => $this->object->passenger_info,  'sellerInfo' => $this->object->seller_info, 'sellerCurrency' => $this->seller->currency->abb];
        lugInfo('PriceQuote data', $depends);
        $this->call = [
            'fields' => view('FlyErbil.PriceQuote', $depends)->render(),
            'url' => $this->cleanUrlSlash($auth['url']),
            'apiMethod' => 'Price Quote',
            'timeout' => 20,
            'headers' => ['Content-Type:application/xml', 'Authorization:Bearer ' . $this->latestSession['accessToken'], 'X-Refresh-Token:' . $this->latestSession['refreshToken']]
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $priceInfo = $this->rawResult['decoded']['PricedItineraries']['PricedItinerary']['AirItineraryPricingInfo'] ?? [];
        if (empty($priceInfo)) {
            $this->addError('no priceInfo');
            return false;
        }
        $totalPrice = $priceInfo['ItinTotalFare']['TotalFare']['@attributes']['Amount'];
        if ($totalPrice != $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable']) {
            lugError('Price missmatch', ['search price' => $this->object->price_info['sellerPrice'], 'PriceQuote price' => $priceInfo]);
            $this->addError("Price Has Changed");
            if ($this->objectIsBooking) {
                $this->failBook();
                $this->addErrorToBook(trans('messages.priceChanged'));
            }
        }
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['beforeBooks']['PriceQuote']  = true;
        $this->object->seller_info = $sellerInfo;
        $this->object->update();
    }
}

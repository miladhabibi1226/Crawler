<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;
use stdClass;

trait PriceQuote
{
    use BeforeBook;
    protected $ticks = [];

    public function sessionHandling()
    {
        if ($this->checkSession($this->object->seller_info['session']) === false) {
            $this->addError('Session Expired.');
            $this->addErrorToBook(trans('messages.session'));
            $this->failBook();
            return false;
        }
        return true;
    }

    protected function decodeRawResult()
    {
        $xml = preg_replace('/ns1:/m', '', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($xml);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//soap:Body')), true);
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $this->initialTripType();
        $depends = $auth + ['tripType' => $this->airTripType, 'queryParams' => $this->queryParams, 'baggageResult' => ['requestData' => $this->object->seller_info['baggageRequest']], 'JSESSIONID' => $this->object->seller_info['JSESSIONID'], 'TransactionIdentifier' => $this->object->seller_info['session'], 'rawODO' => $this->updateSegmentsWithCabinClass($this->object->seller_info['rawODO'])];
        $this->call = [
            'fields' => $this->minifyXML(view('Accelaero.PriceQuote', $depends)->render()),
            'url' => $this->cleanUrlSlash($auth['url']),
            'headers' =>  ["Cookie:{$depends['JSESSIONID']}"],
            'timeout' => 20,
            'apiMethod' => 'Price Quote'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $PricedItinerary = $this->rawResult['decoded'][0]['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary'] ?? [];
        if (empty($PricedItinerary)) {
            lugError('Empty Result', [$this->rawResult['decoded'][0]]);
            $this->addError('Empty Result');
            $this->failBook();
        }
        if (!$this->extraCheck($this->rawResult['decoded'])) {
            lugError('The number of segments in the price result is not equal to the number of segments in the search result', ['search-segments ' => $this->object->service_info, 'price-segments' => $this->rawResult['decoded'][0]['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary']['AirItinerary']]);
            $this->addError('The number of segments in the price result is not equal to the number of segments in the search result');
            $this->failBook();
            return false;
        };
        $this->items = [
            'oldPrice' => $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'],
            'newPrice' => $PricedItinerary['AirItineraryPricingInfo']['ItinTotalFare']['TotalFare']['@attributes']['Amount'] ?? 0,
        ];
        ///> by the management's request we'll compare only the integer numbers of the price
        if (intval($this->items['oldPrice']) != intval($this->items['newPrice'])) {
            lugError('price changed', ['oldPrice' => $this->items['oldPrice'], 'newPrice' => $this->items['newPrice'], 'oldResult' => $this->object->price_info['sellerPrice']['rawPrice'], 'newReult' => $PricedItinerary['AirItineraryPricingInfo'],]);
            $this->addError('price changed');
            $this->failBook();
        }
        $this->priceMapper($PricedItinerary['AirItineraryPricingInfo']);
        lugInfo("Accelaero Flight PriceQuote extractor Items", ['items' => $this->items]);
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass();
        $breakDowns = $price['PTC_FareBreakdowns']['PTC_FareBreakdown'];
        $this->repelSingleMode($breakDowns);
        foreach ($breakDowns as $priceDetails) {

            $costs = $priceDetails['PassengerFare'];

            $supplierTaxes =  $costs['Taxes']['Tax'] ?? [];
            $this->repelSingleMode($supplierTaxes);
            $totalTax = ($totalTax ?? 0) + array_sum(array_map(function ($tax) {
                return $tax['@attributes']['Amount'];
            }, $supplierTaxes));

            $supplierFees = $costs['Fees']['Fee'] ?? [];
            ///> adding baggage fees manually
            $this->repelSingleMode($supplierFees);
            $totalFees = ($totalFees ?? 0) + array_sum(array_map(function ($fee) {
                return $fee['@attributes']['Amount'];
            }, $supplierFees));
        }

        // lugWarning("BaseFare {$price['ItinTotalFare']['BaseFare']['@attributes']['Amount']}", [$price]);
        // lugWarning("TotalFare {$price['ItinTotalFare']['TotalFare']['@attributes']['Amount']} AED");
        // lugWarning("totalFees $totalFees");
        // lugWarning("totalTax $totalTax");

        return $newPrice;
    }

    protected function tryAnotherWayForPriceInfo($price)
    {
        $newPrice = new stdClass();
        $breakDowns = $price['PTC_FareBreakdowns']['PTC_FareBreakdown'];
        $this->repelSingleMode($breakDowns);
        foreach ($breakDowns as $priceDetails) {
            $costs = $priceDetails['PassengerFare'];

            $supplierTaxes =  $costs['Taxes']['Tax'] ?? [];
            $this->repelSingleMode($supplierTaxes);
            $totalTax = array_sum(array_map(function ($tax) {
                return $tax['@attributes']['Amount'];
            }, $supplierTaxes));

            $supplierFees = $costs['Fees']['Fee'] ?? [];
            $this->repelSingleMode($supplierFees);
            $totalFees = array_sum(array_map(function ($fee) {
                return $fee['@attributes']['Amount'];
            }, $supplierFees));

            $originalBreakDown  = [
                'fee' => ($originalBreakDown['fee'] ?? 0) + $totalFees,
                'tax' => ($originalBreakDown['tax'] ?? 0) + $totalTax,
            ];
        }

        // lugWarning("BaseFare {$price['ItinTotalFare']['BaseFare']['@attributes']['Amount']}", [$price]);
        // lugWarning("TotalFare {$price['ItinTotalFare']['TotalFare']['@attributes']['Amount']} AED");
        // lugWarning("totalFees {$originalBreakDown['fee']}");
        // lugWarning("totalTax {$originalBreakDown['tax']}");
        return $newPrice;
    }
    
    protected function mapper()
    {
        $this->object->seller_info = array_merge($this->object->seller_info ?? [], $this->items['sellerInfo'] ?? []);
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['beforeBooks']['PriceQuote']  = true;
        $sellerInfo['PriceQuoteResult'] = $this->items;
        $this->object->seller_info = $sellerInfo;
        $this->object->update();
    }
}

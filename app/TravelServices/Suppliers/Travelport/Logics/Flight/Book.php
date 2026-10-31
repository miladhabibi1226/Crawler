<?php

namespace App\TravelServices\Suppliers\Travelport\Logics\Flight;

use App\TravelServices\Logics\Flight\Book as FlightBook;

trait Book
{
    use FlightBook;

    protected function decodeRawResult()
    {
        $xml = preg_replace('/<(\/?)(\w+):(\w+)/', '<$1$3', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($xml);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//Body')), true)[0] ?? [];
    }

    public function requestGenerator()
    {
        $hostTokenRefs = [];
        $auth = $this->seller->api->configs;
        foreach ($this->object->seller_info['AirPricingSolution']['AirPricingInfo'] as $airPricingInfo) {
            foreach ($airPricingInfo['BookingInfo'] as $bookingInfo) {
                $hostTokenRefs[] = $bookingInfo['HostTokenRef'];
            }
        }
        $depends = $this->object->service_info + $auth + $this->object->seller_info + ['passengers' => $this->object->passenger_info, 'hostTokenRefs' => array_values(array_unique($hostTokenRefs))];
        lugInfo('book data',[$depends]);
        $this->call = [
            'fields' => $fields = $this->minifyXML(view('Travelport.FlightBook', $depends)->render()),
            'url' =>   $this->cleanUrlSlash($auth['url']),
            'headers' => [
                "Content-length: " . strlen($fields)
            ],
            'apiMethod' => 'Book'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $pnr = [];
        $BookResult = $this->rawResult['decoded'] ?? '';
        //check empty result
        if (empty($BookResult)) {
            lugError('empty book result', ['this->rawResult' => $this->rawResult]);
            $this->failBook();
            $this->addError('empty book result');
            return false;
        }
        //check pnr
        $pnr = $BookResult['AirCreateReservationRsp']['UniversalRecord']['ProviderReservationInfo']['@attributes']['LocatorCode'] ?? '';
        $airlinePnr = $BookResult['AirCreateReservationRsp']['UniversalRecord']['AirReservation']['@attributes']['LocatorCode'] ?? '';
        if (empty($pnr)) {
            lugError('no Pnr  in book_result', ['this->rawResult' => $this->rawResult]);
            $this->failBook();
            $this->addError('no pnr in book_result');
            return false;
        }
        //initial $this->items
        $this->items['bookingInfo'] = [
            'pnr' => [$pnr],
            'airlinePnr' => $airlinePnr,
            'expires_at' => ''
        ];
        $this->items['sellerInfo'] = [
            'AirPricingSolution' => [],
            'bookingResult' => [
                'extrapnr' => $BookResult['AirCreateReservationRsp']['UniversalRecord']['@attributes']['LocatorCode'] ?? '',
            ],
            'supplierReference' => $BookResult['AirCreateReservationRsp']['UniversalRecord']['AirReservation']['@attributes']['LocatorCode'] ?? '',
            'supplierCode' => $BookResult['AirCreateReservationRsp']['UniversalRecord']['AirReservation']['SupplierLocator']['@attributes']['LocatorCode'] ?? '',

        ];

        lugInfo('TravelPort Book extractor Items', ['items' => $this->items]);
    }
}

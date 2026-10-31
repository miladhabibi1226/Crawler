<?php

namespace App\TravelServices\Suppliers\FlyErbil\Logics\Flight;

use App\TravelServices\Logics\Flight\Book as FlightBook;

trait Book
{
    use FlightBook;
    protected $responseSample = 'Samples/FlyErbil/FlightBook.xml';

    public function sessionHandling()
    {
        return $this->checkSession($this->object->seller_info['session']);
    }

    public function requestGenerator()
    {
        $this->latestSession = json_decode($this->supplierSession->extras, true);
        $auth = $this->seller->api->configs;
        $depends = $auth + ['passengers' => $this->object->passenger_info,  'sellerInfo' => $this->object->seller_info, 'sellerCurrency' => $this->seller->currency->abb];
        lugInfo('Book data', $depends);
        $this->call = [
            'fields' => view('FlyErbil.FlightBook', $depends)->render(),
            'url' => $this->cleanUrlSlash($auth['url']),
            'apiMethod' => 'Air Book',
            'headers' => ['Content-Type:application/xml', 'Authorization:Bearer ' . $this->latestSession['accessToken'], 'X-Refresh-Token:' . $this->latestSession['refreshToken']]
        ];
        return $this->action();
    }

    protected function decodeRawResult()
    {
        $this->rawResult['body'] = preg_replace('/(<ota:OTA_AirBookRS)([^>]+)>/', '$1>', $this->rawResult['body']);
        $this->rawResult['body'] = preg_replace('/ota:/m', '', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml), true);
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded']['AirReservation']['BookingReferenceID'] ?? $this->addError('empty BookingInfo');
        $ticketingInfo = $this->rawResult['decoded']['AirReservation']['Ticketing'] ?? $this->addError('empty ticket Expiry Info');
        $priceInfo = $this->rawResult['decoded']['AirReservation']['PriceInfo'] ?? $this->addError('empty price Expiry Info');
        $rawInfo = ['BookingReferenceID' => $BookResult, 'Ticketing' => $ticketingInfo, 'priceInfo' => $priceInfo];
        $passengerCount = $this->object->service_info['query_params']['adults'] + ($this->object->service_info['query_params']['children'] ?? 0) + ($this->object->service_info['query_params']['infants'] ?? 0);

        $this->items['sellerInfo'] = [
            'rawBookingDetails' => $rawInfo,
            'totalPrice' => $totalPrice = $priceInfo['ItinTotalFare']['TotalFare']['@attributes']['Amount'],
            'pnrType' => $BookResult['@attributes']['Type'],
        ];

        if ($totalPrice != $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable']) {
            lugError('Price missmatch', ['search price' => $this->object->price_info['sellerPrice'], 'booking price' => $totalPrice]);
        }

        $this->items['bookingInfo'] = [
            'pnr' => [$BookResult['@attributes']['ID']],
            'expires_at' => $ticketingInfo['@attributes']['TicketTimeLimit'] ?? null,
            'passenger_count' => $passengerCount ?? null
        ];

        lugInfo('FlyErbil Flight Book extractor Items', ['items' => $this->items]);
    }

    protected function handleSignOut()
    {
        $this->callSignOut($this->object->seller_info['session']);
    }
}

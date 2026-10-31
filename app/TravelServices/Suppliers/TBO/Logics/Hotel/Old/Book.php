<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\Old;

use App\TravelServices\Logics\Hotel\Book as HotelBook;
use Carbon\Carbon;

trait Book
{
    use HotelBook;
    ///> BookAndIssue uses requestGenerator
    protected $responseSample = 'Samples/TBO/Book.xml';

    public function sessionHandling()
    {
        return $this->checkSession($this->object->seller_info['session']);
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $rooms = [];
        foreach ($this->object->service_info['selectedCombination'] as $key => $room) {
            $roomServiceInfo = $room;
            $roomPriceInfo = $this->object->price_info['selectedCombination']['sellerPrice']['details']['bySellerCurrency']['roomsFare'][$key];
            $rooms[] = [
                'serviceInfo' => $roomServiceInfo,
                'price' => $roomPriceInfo
            ];
        }
        $depends = $this->object->seller_info + $auth + [
            'clientRef' => $this->getClientReference(),
            'passengers' => $this->object->passenger_info,
            'guestNationality' => $this->object->service_info['query_params']['nationality_country']['abb'],
            'rooms' => $rooms,
            'voucherBooking' => $this->bookingType == 'bookOnly' ? 'false' : 'true'
        ];
        lugInfo('Blade Data', [$depends]);
        $this->call = [
            'fields' => view('TBO.Book', $depends)->render(),
            'url' => $auth['url'],
            'apiMethod' => 'HotelBook'
        ];
        return $this->action();
    }

    protected function decodeRawResult()
    {
        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
    }

    public function extractor()
    {
        $result = $this->rawResult['decoded'][0]['HotelBookResponse'] ?? $this->callException('empty Response');
        if (empty($result['ConfirmationNo'])) {
            $this->failBook();
            $this->addError('No PNR');
            return;
        }
        $this->items['bookingInfo'] = [
            'pnr' => $result['ConfirmationNo'],
            'expires_at' => $this->getExpiryTime()
        ];
        $this->items['sellerInfo'] = [
            'bookingResult' => [
                'status' => $result['BookingStatus'] ??  '',
                'tripId' => $result['TripId'] ?? null,
                'priceChange' => $result['PriceChange']['@attributes']['Status'] ?? false
            ]
        ];
        lugInfo("TBO Hotel Book extractor Items", ['items' => $this->items]);
    }

    public function getClientReference()
    {
        $today = Carbon::now();

        $length = 4;
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $today->isoFormat('DD') . $today->isoFormat('MM') . $today->isoFormat('YY') . $today->isoFormat('HH') . $today->isoFormat('mm') . $today->isoFormat('ss') . $today->isoFormat('SSS') . '#' . $randomString;
    }
}

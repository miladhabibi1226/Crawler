<?php

namespace App\TravelServices\Suppliers\Avtra\Logics\Flight;

use App\TravelServices\Logics\Flight\Book as FlightBook;
use Carbon\Carbon;

trait Book
{
    use FlightBook;
    ///> Issue and BookAndIssue uses getContent requestGenerator

    protected function decodeRawResult()
    {
        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml), true);
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + [
            'passengers' => $this->object->passenger_info,
            'sellerInfo' => $this->object->seller_info,
            'time' => Carbon::now()->toIso8601String(),
            'sellerCurrency' => $this->seller->currency->abb,
            'price' => [
                'payable' => $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'],
                'baseFare' => $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['baseFare']
            ]
        ];
        lugInfo('Book data', $depends);
        $this->call = [
            'fields' => view('Avtra.FlightBook', $depends)->render(),
            'url' => $this->cleanUrlSlash($auth['url']) . '/booking/create',
            'apiMethod' => $this->bookingType == 'bookOnly' ? 'Book' : 'BookAndIssue'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded']['AirReservation'] ?? null;

        if (empty($BookResult)) {
            lugError('empty AirReservation', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('empty AirReservation');
        }

        preg_match('/<AirItinerary DirectionInd=(.*?)<\/AirItinerary>/ims', $this->rawResult['body'], $odOptions);
        $xpires_at = $BookResult['Ticketing']['@attributes']['TicketTimeLimit'] ?? '';
        $pnr = (string) $BookResult['BookingReferenceID']['@attributes']['ID'] ?? null;
        $ticketingStatus = $BookResult['BookingReferenceID']['@attributes']['Status'];

        $this->items['bookingInfo'] = [
            'pnr' => (array) $pnr,
            'expires_at' => $xpires_at ?? null
        ];
        $this->items['sellerInfo'] = [
            'supplierBookingStatus' =>  ['39' => 'CONFIRMED', '16' => 'CANCELLED', '1' => 'TTL'][$ticketingStatus],
            'issueRequestData' => $odOptions[0] ?? null,
        ];

        lugInfo("Avtra Flight Book extractor Items", ['items' => $this->items]);
    }
}

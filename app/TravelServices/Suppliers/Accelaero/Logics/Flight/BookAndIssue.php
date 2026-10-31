<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight;

use Carbon\Carbon;

trait BookAndIssue
{
    protected $ticks = [];

    public function canBeDone()
    {
        if ($this->checkSession($this->object->seller_info['session']) === false) {
            $this->object->booking_info = ['error' => trans('messages.session')];
            $this->object->status = 'failed';
            $this->object->update();
            $this->addError('Session Expired.');
            return false;
        }
        return true;
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $xml = file_get_contents(storage_path('Samples/Accelaero/FlightBook.xml'));
            $xml = preg_replace('/ns1:/m', '', $xml);
            $this->rawResult['body'] = $xml;
            $xml = new \SimpleXMLElement($xml);
            $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//soap:Body')), true);
            $this->latestSession = 'fromFile';
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            $this->failBook();
            return false;
        }
        $xml = preg_replace('/ns1:/m', '', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($xml);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//soap:Body')), true);

        if ($this->haveSupplierResponseError()) {
            $this->addError("Supplier has error");
            $this->failBook();
            return false;
            $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//soap:Body')), true);
        }
    }

    public function requestGenerator()
    {
        ///> by suppliers request we'll add 3 sec sleep before calling book api. (email title: Travelergate - Accelaer | Error in the book api for airarabia)
        sleep(3);
        $auth = $this->seller->api->configs;
        $depends = $auth + ['currency' => $this->seller->currency->abb, 'payable' => $this->object->seller_info['PriceQuoteResult']['newPrice'], 'passengers' => $this->object->passenger_info,  'JSESSIONID' => $this->object->seller_info['JSESSIONID'],  'TransactionIdentifier' => $this->object->seller_info['session'], 'rawODO' =>  $this->updateSegmentsWithCabinClass($this->object->seller_info['rawODO'])];
        lugInfo('Book data', [$depends]);
        $this->call = [
            'fields' => $this->minifyXML(view('Accelaero.FlightBook', $depends)->render()),
            'url' => $this->cleanUrlSlash($auth['url']),
            'headers' => ["Cookie:{$depends['JSESSIONID']}"],
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded'][0]['OTA_AirBookRS'] ?? $this->callException('empty result');
        $this->objectStatus = 'issued';
        $pnr = [$BookResult['AirReservation']['BookingReferenceID']['@attributes']['ID'] ?? null];
        if (empty($pnr)) {
            $this->objectStatus = 'waiting_issue';
        }
        $this->items['bookingInfo'] = [
            'issued_at' =>  Carbon::now(),
            'tickets' => $tickets = $this->flightTicketInfo($this->getTickets($BookResult['AirReservation']['TravelerInfo']['AirTraveler'] ?? [])),
            'pnr' => array_unique($pnr ?? [])
        ];
        $this->items['pnr'] = implode('.', array_unique($pnr)) . ':' . implode(',', array_unique($this->ticks));
        lugInfo("Accelaero Flight $this->action extractor Items", ['items' => $this->items, 'ticks' => $this->ticks]);
    }

    protected function getTickets($travelerInfo)
    {
        $legSegments = $this->getLegSegments();
        $this->repelSingleMode($travelerInfo);
        $passengers = [];
        foreach ($travelerInfo as $traveler) {
            $ticketNumber = [];
            $ticketInfo = $traveler['ETicketInfo']['ETicketInfomation'];
            $this->repelSingleMode($ticketInfo);
            foreach ($ticketInfo as $info) {
                $segment = $legSegments[$info['@attributes']['flightSegmentCode']];
                $ticketNumber[$segment['legIndex']][$segment['segmentIndex']] = $info['@attributes']['eTicketNo'] ?? '';
                if (!empty($info['@attributes']['eTicketNo'])) {
                    $this->ticks[] = $info['@attributes']['eTicketNo'];
                }
            }
            $passengers[strtoupper(str_replace(' ', '', $traveler['PersonName']['Surname'] . $traveler['PersonName']['GivenName']))] = $ticketNumber;
        }
        return $passengers;
    }

    protected function mapper()
    {
        $seller_info = $this->object->seller_info;
        unset($seller_info['rawODO']);
        unset($seller_info['baggageRequest']);
        $this->object->seller_info = $seller_info;

        $booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingInfo']);
        $this->object->booking_info = $booking_info;

        $this->object->pnr =  $this->items['pnr'];
        $this->object->booked_at = $this->items['bookingInfo']['issued_at'];
        $this->object->status = $this->objectStatus;
        $this->object->update();
        $this->itemIssued($this->items['bookingInfo']['pnr']);
    }

    public function done()
    {
        $this->sellerTransaction();
        $this->callSignOut($this->object->seller_info['session']);
        return ['status' => true];
    }
}

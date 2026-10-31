<?php

namespace App\TravelServices\Suppliers\TopTravel\Logics\Flight;

use Carbon\Carbon;

trait BookingStatus

{

    public function canBeDone()
    {
        return $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/TopTravel/FlightBook.json'));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->requestGeneratorFailed();
                return false;
            }
        }
        $this->decodeRawResult();
        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->seller_info + ['passengers' => $this->object->passenger_info];
        lugInfo('bookingStatus data', $depends);
        $this->call = [
            'fields' => json_encode(['otaTransactionId' => $this->object->seller_info['otaTransactionId']]),
            'url' =>   $this->cleanUrlSlash($auth['url']) . '/satguruapiout/v1/book/bookingStatus',
            'headers' => [
                'Authorization:Bearer ' . $this->latestSession,
                'Content-Type: application/json'
            ],
            'apiMethod' => 'bookingStatus'
        ];

        return $this->action();
    }


    public function extractor()
    {
        $bookStatusResult = $this->rawResult['decoded'] ?? [];
        //check empty
        if (empty($bookStatusResult['bookingStatus'])) {
            lugError('empty bookingStatus in book result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->contactAdminStatus();
            return $this->addError('response is invalid:');
        }
        $this->objectStatus = 'issued';
        //check status
        if ($bookStatusResult['bookingStatus'] === 'Booking Failed') {
            lugError('book faield', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('response is invalid:');
        }
        if ($bookStatusResult['bookingStatus'] !== 'Booked') {
            $this->objectStatus = 'incomplete';
        }
        if ($bookStatusResult['bookingStatus'] === 'Booked' && empty($bookStatusResult['flightDetails'][0]['airlinePnr'])) {
            lugError('empty pnr - status => Booked', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->contactAdminStatus();
            return $this->addError('response is invalid:');
        }
        //check pnr
        if (empty($bookStatusResult['flightDetails'][0]['airlinePnr'])) {
            lugError('empty airlinePNR', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError(trans('technical'));
            return $this->contactAdminStatus();
        }
        $this->items['bookingInfo'] = [
            'pnr' => $pnrs = array_column($bookStatusResult['flightDetails'], 'airlinePnr'),
            'supplierReference' => $spPnr = implode(',', array_unique(array_column($bookStatusResult['flightDetails'], 'spPnr'))),
            'tickets' => $this->flightTicketInfo($this->getTickets($bookStatusResult)),
            'issued_at' =>  Carbon::now()
        ];
        $this->items['sellerInfo'] = ['supplierReference' => $spPnr];
        if (empty($this->ticks[0])) {
            lugError('No thicket');
            $this->objectStatus = 'incomplete';
        }
        $this->items['pnr'] = implode('.', array_unique($pnrs)) . ':' . implode(',', array_unique($this->ticks));

        lugInfo('TopTravel Flight BookingStatus extractor Items', ['items' => $this->items]);
    }

    protected function decodeRawResult()
    {
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
    }

    protected function getTickets($bookStatusResult)
    {
        $legSegments = $this->getLegSegments();
        $ticketNumber = [];
        foreach ($bookStatusResult['passengerDetails'] as $key => $traveler) {
            foreach ($traveler['sector'] as $index => $sector) {
                $segment =  $legSegments[str_replace('-', '/', $sector)];
                $this->ticks[] =   $ticketNumber[$segment['legIndex']][$segment['segmentIndex']] = $traveler['ticketNo'][$index] ?? '';
            }
            $nameParts = explode(" ", trim($traveler['passengerName']));
            $firstName = strtoupper($nameParts[0]);
            $lastName = strtoupper(end($nameParts));
            $formattedName = $lastName . $firstName;
            $passengers[$formattedName] = $ticketNumber ?? '';
        }
        return $passengers;
    }


    protected function mapper()
    {
        $this->object->seller_info = array_merge($this->object->seller_info ?? [], $this->items['sellerInfo'] ?? []);
        $this->object->booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingInfo'] ?? []);
        $this->object->booked_at = Carbon::now();
        if ($this->bookingType == 'bookOnly') {
            $this->object->status = 'on_hold';
            $expireOn = $this->checkExpiryTime(Carbon::create($this->items['bookingInfo']['expires_at']));
            $this->object->expire_on = $expireOn;
        }
        if (!empty($this->items['bookingInfo']['pnr'][0])) {
            $this->object->pnr = implode(',', (array) $this->items['bookingInfo']['pnr']) . ':';
        }
        $this->object->status = $this->objectStatus;
        $this->object->update();
    }
    protected function done()
    {
        return ['status' => true];
    }
}

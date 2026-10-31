<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\DummyTravelService;

use Carbon\Carbon;

trait Issue
{
    protected $latestSession, $needsSchedule = false, $ticks = [];

    public function canBeDone()
    {
        if ($this->object->status != 'on_hold') {
            $this->addError('Invalid Booking status: ' . $this->object->status);
            return false;
        }
        if ($this->bookingType == 'bookOnly') {
            if (Carbon::create($this->object->expire_on) < Carbon::now()) {
                $this->object->status = 'expired';
                $this->object->update();
                $this->addError('The booking has been expired');
                return false;
            }
        }
        return true;
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/DummyName/DummyTravelServiceIssue.json'));
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Request generator unsuccessful");
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + ['bookingInfo' => $this->object->booking_info];
        lugInfo('Issue Data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' =>  $auth['url'],
            'apiMethod' => 'issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $ticketResult = $this->rawResult['body'] ?? $this->callException('empty result');
        $this->objectStatus = 'issued';
        if (empty($ticketResult['pnrCode'])) {
            lugError('No PNR');
            $this->objectStatus = 'waiting_issue';
            $this->needsSchedule = true;
        }
        $this->items['bookingInfo'] = [
            'pnr' => [$ticketResult['pnrCode']],
            'tickets' => $this->flightTicketInfo($this->getTickets($ticketResult['passengers'] ?? [])),
            'issued_at' =>  Carbon::now()
        ];
        $this->items['issueResult'] = [];
        $this->items['pnr'] = $ticketResult['pnrCode'] . ':' . implode(',', array_unique($this->ticks));

        lugInfo('DummyName DummyTravelService Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info, $this->items);
        $this->object->status =  $this->objectStatus;
        $this->object->pnr = $this->items['pnr'];
        $this->object->update();
        $this->itemIssued($this->items['bookingInfo']['pnr']);
    }

    protected function getTickets($ticketResult)
    {
        $passengers = [];
        foreach ($ticketResult as $traveler) {
            if (empty($traveler['ticketNumber'])) {
                $this->objectStatus = 'waiting_issue';
                $this->needsSchedule = true;
            }
            $ticketNumber = $traveler['ticketNumber'];
            $passengers[strtoupper(str_replace(' ', '', $traveler['lastName'] . $traveler['firstName']))] = $ticketNumber;
        }
        return $passengers;
    }

    protected function getFields($depends)
    {
        $fields = [];
        return $fields;
    }

    protected function done()
    {
        $this->sellerTransaction();
        return ['status' => true];
    }
}

<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\DummyTravelService;

use Carbon\Carbon;

trait Issue
{
    protected $latestSession;

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
            'cookies' => null,
            'apiMethod' => 'issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $ticketResult = $this->rawResult['body'] ?? $this->callException('empty result');

        $this->items = [];

        $this->items['bookimgInfo'] = [
            'tickets' => $tickets ?? [],
            'pnr' => $pnr ?? []
        ];
        $this->items['pnr'] = $this->object->booking_info['pnr'][0] . ':' . implode(',', array_unique(array_merge(...array_column($result->tickets, 'ticket_number'))));
        lugInfo('DummyName DummyTravelService Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $booking_info = $this->object->booking_info;
        $seller_info = $this->object->seller_info;
        $booking_info = array_merge($this->object->booking_info, $this->items);
        $booking_info['issued_at'] = Carbon::now();
        $this->object->booking_info = $booking_info;
        $this->object->seller_info = $seller_info;
        $this->object->status =  'issued';
        $this->object->update();
    }

    protected function getFields($depends)
    {
        $fields = [];
        return $fields;
    }
}

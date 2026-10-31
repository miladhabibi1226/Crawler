<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\DummyTravelService;

use Carbon\Carbon;

trait Book
{
    public function canBeDone()
    {
        if ($this->object->status != 'validated') {
            $this->addError('Invalid Booking status: ' . $this->object->status);
        }

        if ($this->bookingType == 'bookOnly' && $this->object->seller_info['onholdable'] == false) {
            $this->addError('Can not on-hold this item');
            lugWarning('Can not onhold this item', ['bookingType' => $this->bookingType, 'onholdable' => $this->object->seller_info]);
            $this->failBook();
            return;
        }
        return true;
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/DummyName/DummyTravelServiceBook.json'));
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Request generator unsuccessful");
                $this->failBook();
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->failBook();
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->seller_info + ['passengers' => $this->object->passenger_info];
        lugInfo('Book data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' =>  $auth['url'],
            'headers' => [],
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded'];

        if (empty($BookResult)) {
            lugError('empty result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('empty result');
        }

        $this->checkForPriceChange($BookResult['totalFare']);

        $this->items = [
            'pnr' => [''],
            'expires_at' => '' ?? null
        ];

        lugInfo('DummyName DummyTravelService Book extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $now = Carbon::now();
        $this->object->status =  'on_hold';
        $this->object->booked_at = $now;
        $booking_info = array_merge($this->object->booking_info ?? [], $this->items);
        $this->object->booking_info = $booking_info;
        $this->object->pnr = implode(',', $this->items['pnr']) . ':';
        if ($this->bookingType == 'bookOnly') {
            $expireOn =  $this->checkExpiryTime(Carbon::create($this->items['expires_at']));
            $this->object->expire_on = $expireOn;
        }
        $this->object->update();
    }

    protected function getFields($depends)
    {
        $fields = [];
        return $fields;
    }
}

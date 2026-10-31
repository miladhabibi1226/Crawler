<?php

namespace App\TravelServices\Suppliers\Sepehr\Logics\Flight;

use Carbon\Carbon;

trait BookAndIssue
{
    protected $ticks = [];
    protected function canBeDone()
    {
        if ($this->object->status != 'validated') {
            $this->addError('Invalid Booking status: ' . $this->object->status);
        }
        if ($this->object->seller_info['supplierVersion'] != $this->seller->api->configs['version']) {
            $this->addError('Invalid supplierVersion: ' . $this->object->seller_info['supplierVersion']);
        }
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path($this->getBookSampleAddress()));
            $this->latestSession = 'fromFile';
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Request generator unsuccessful");
                $this->failBook();
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
        if ($this->haveSupplierResponseError()) {
            $this->failBook();
            $this->addError("Item is not available anymore, or the price has changed. Please repeat your search");
            return false;
        }
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth + [
            'passengers' => $this->object->passenger_info,
            'payable' => $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'],
            'referenceId' => $this->object->reference_id
        ];
        $depends['password'] = hash('MD5', $auth['password']);
        lugInfo('Blade Data', [$depends]);
        $this->call = [
            'fields' =>  json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($auth['url']) . $this->getBookUrl(),
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    protected function mapper()
    {
        $this->object->seller_info = array_merge($this->object->seller_info ?? [], $this->items['sellerInfo'] ?? []);
        $this->object->booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingInfo'] ?? []);
        $this->object->status = $this->objectStatus;
        $this->object->booked_at = Carbon::now();
        $this->object->unlock_on = null;
        $this->object->pnr = $this->items['pnr'];
        $this->object->update();
        $this->itemIssued($this->items['bookingInfo']['pnr']);
    }

    protected function done()
    {
        $this->sellerTransaction();
        return ['status' => true];
    }
}

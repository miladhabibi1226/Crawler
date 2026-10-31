<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics\Flight;


trait BookingDetails
{
    protected function canBeDone()
    {
        $this->authorizeRequest($this->object->seller_info['bookingSession'] ?? '');
    }

    protected function getContent()
    {
        if ($this->fromFile) {
            $this->rawResult = ['http_code' => 200, 'body' => file_get_contents(storage_path('Samples/Navitaire/BookingDetails.json'))];
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
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
        $depends = $this->seller->api->configs;
        lugInfo('BookingDetails data', [$depends]);
        $this->call = [
            'fields' => '',
            'method' => 'GET',
            'url' =>  "{$depends['url']}/api/jz/v1/BookingDetails?BookingDetailsType=BalanceDue",
            'apiMethod' => 'BookingDetails'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $Result = $this->rawResult['decoded']['data']['booking'] ?? [];
        if (empty($Result)) {
            lugError('empty booking', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->objectStatus = 'contact_admin';
            return $this->addError('empty Result');
        }
        $this->objectStatus = 'issued';
        if (strtolower($Result['info']['status']) != 'confirmed') {
            lugError('Result', ['Result' => $Result]);
            $this->objectStatus = 'contact_admin';
            return $this->addError('invalid Result');
        }
        if (strtolower($Result['info']['paidStatus']) != 'paidinfull') {
            lugError('Result', ['Result' => $Result]);
            $this->objectStatus = 'contact_admin';
            return $this->addError('invalid Result');
        }
        $this->items = $Result['info'];
        lugInfo('Navitaire BookingDetails extractor Items', ['Items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->status = $this->objectStatus;
        $this->object->update();
        if ($this->objectStatus == 'issued') {
            $this->itemIssued($this->object->booking_info['pnr']);
        }
    }

    public function done()
    {
        if ($this->objectStatus == 'issued') {
            $this->sellerTransaction();
        }
        $this->callSignOut($this->object->seller_info['bookingSession']);
        return ['status' => true];
    }
}

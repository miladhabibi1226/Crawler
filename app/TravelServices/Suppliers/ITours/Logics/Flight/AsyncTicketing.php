<?php

namespace App\TravelServices\Suppliers\ITours\Logics\Flight;


trait AsyncTicketing
{
    use Issue;

    public function canBeDone()
    {
        if (in_array($this->object->status, ['issued'])) {
            $this->addError('Invalid Booking status: ' . $this->object->status);
            return false;
        }

        $this->object->status = 'waiting_issue';
        $this->object->update();

        $this->authorizeRequest();
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/ITours/GetReserveDetail.json'));
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
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
        $key = $this->object->seller_info['bookingRequestData']['reserveId'];
        $this->call = [
            'fields' => '',
            'method' => 'GET',
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/Partners/Flight/V2/GetReserveDetail?reserveId=$key",
            'apiMethod' => 'GetReserveDetail'
        ];
        return $this->action();
    }
}

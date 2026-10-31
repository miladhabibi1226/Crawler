<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics\Flight;

trait Confirm
{

    protected function canBeDone()
    {
        return $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['decoded'] = json_decode(file_get_contents(storage_path('Samples/Parto/FlightBookingData.json')), true);
            return true;
        }
        if ($this->requestGenerator() === false) {
            $this->addError("Request generator unsuccessful");
            $this->object->status = 'incomplete';
            $this->object->update();
            return false;
        }

        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            $this->object->update();
            return false;
        }
    }

    public function requestGenerator()
    {
        $this->call = [
            'fields' => json_encode(['refrence_id' => $this->object->seller_info['bookingResult']['refrence_id']]),
            'url' =>  $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/v2/flight/Confirm",
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'Confirm'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $bookDataResult = $this->rawResult['decoded'];
        if ($bookDataResult['status'] != 'progress') {
            return false;
        }

        lugInfo('Sepehran Confirm Status: ' . $bookDataResult['status']);
    }

    protected function mapper() {}

    protected function haveSupplierResponseError()
    {
        if (($this->rawResult['decoded']['status'] ?? '') != 'progress') {
            $rawErrors = array_column($this->rawResult['decoded']['err'] ?? [], "msg");
            $rawError = implode(". ", $rawErrors);
            lugWSResError("Sepehran response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError ?? 'Sepehran response contains error');
            return $rawError ?? 'Sepehran response contains error';
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("Sepehran response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'Sepehran response is invalid';
        }
        $this->supplierConversaion();
        return false;
    }
}

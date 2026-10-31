<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Hotel;

trait CreateBook
{

    protected function canBeDone() {}

    protected function getContent()
    {
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Quantum/HotelGetRoomInformation.json'));
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $this->latestSession = 'fromFile';
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
        $auth = $this->seller->api->configs;
        $depends = [
            'referenceId' => $this->object->seller_info['bufferReferenceId'],
            'selectedCombination' => $this->object->seller_info['selectedCombination'],
            'serviceInfoSelectedCombination' => $this->object->service_info['selectedCombination'],
            'combinationPrice' => $this->object->service_info['combinationPrice'],
            'type' => "hotel",
            'action' => request()->action
        ];
        // lugInfo('Request Data', [$depends]);
        $this->call = [
            'api' => 'v0/createbook',
            'host' => $auth['url'],
            'fields' => $depends,
            'apiMethod' => 'Create Book'
        ];
        return $this->action();
    }

    protected function extractor() {}


    protected function mapper() {}
}

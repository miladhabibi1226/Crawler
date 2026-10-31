<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;

trait FareRules
{
    use FlightFareRules;
    protected function getContent()
    {
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
        $this->LugTheResponse = false;
        $this->LugTheRequest = false;
        $auth = $this->seller->api->configs;
        $depends = [
            'referenceId' => $this->object->seller_info['bufferReferenceId'],
            'type' => "flight",
            'action' => "fareRules"
        ];
        lugInfo('Request Data', [$depends]);
        $this->call = [
            'api' => 'v0/afterSearch',
            'host' => $this->cleanUrlSlash($auth['url']),
            'fields' => $depends,
            'apiMethod' => 'Fare Rules'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $rawResult = $this->rawResult['decoded']['data'];
        $this->items = $rawResult['fareRules'];
        // lugInfo('Quantum fare rules extractor item', ['items' => $this->items]);
    }
}

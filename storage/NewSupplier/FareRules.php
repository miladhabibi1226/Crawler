<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;

trait FareRules
{
    use FlightFareRules;
    public function canBeDone()
    {
        $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        if ($this->fromFile) {
            $this->rawResult['decoded'] = json_decode(file_get_contents(storage_path('Samples/DummyName/FlightRules.json')), true);
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Request generator unsuccessful");
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
        $this->call = [
            'fields' => json_encode($this->getFields()),
            'url' =>  $this->seller->api->configs['url'] . "",
            'apiMethod' => 'Fare Rules'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $this->items = [];
        $fareRulesResult = $this->rawResult['decoded']['FareRules'] ?? [];
        // lugInfo('DummyName fareRules extractor Items', ['items' => $this->items]);
    }
}

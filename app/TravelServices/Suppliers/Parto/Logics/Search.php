<?php

namespace App\TravelServices\Suppliers\Parto\Logics;

trait Search
{
    protected $latestSession, $translatedQueryParams;
    public function canBedone()
    {
        $this->findOrCreateSupplierSession();
        if (!$this->translatedQueryParams = $this->translateQueryParams()) {
            return $this->addError('Query params translation faoled');
        }
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $travelService = ucfirst($this->travelService);
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/Parto/{$travelService}Search.json"));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }

        // lugInfo('Parto Search request', ['request body' => $this->call['fields'], 'url' => $this->call['url'], 'time' => \Carbon\Carbon::now()->format('Y-m-d H:i:s'), 'response' => $this->rawResult['body']]);

        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    protected function requestGenerator()
    {
        // $this->LugTheResponse = 1;
        // lugInfo("Parto Search Data ({$this->seller->alias})", [$this->queryParams, $this->latestSession]);
        $api = $this->travelService == 'flight' ? '/Rest/Air/AirLowFareSearch' : '/Rest/Hotel/HotelAvailability'; ///> bc this function is used by both flight and hotel search
        $this->call = [
            'fields' => json_encode($this->getFields()),
            'url' => $this->seller->api->configs['url'] . $api,
            'timeout' => 30,
            'apiMethod' => 'Search'
        ];
        return $this->action();
    }
}

<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\DummyTravelService;

use App\TravelServices\Logics\DummyTravelService\Search as DummyTravelServiceSearch;

trait Search
{
    use DummyTravelServiceSearch;

    protected $latestSession;
    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/CityNet/DummyTravelServiceSearch.json'));;
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
        // $this->LugTheResponse = 1;
        $auth = $this->seller->api->configs;
        $depends = [];
        lugInfo('Search Data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' =>  $auth['url'],
            'cookies' => $this->latestSession ?? null,
            'timeout' => 20
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded'] ?? $this->callException('empty result');

        $this->items = $rawResults;

        lugInfo('DummyName search Extractor items', ['Count' => count($this->items), 'First 3 Items' => array_slice($this->items ?? [], 0, 3)]);
    }

    protected function priceMapper($source)
    {
    }

    protected function getFields($depends)
    {
        $fields = [];
        return $fields;
    }
}

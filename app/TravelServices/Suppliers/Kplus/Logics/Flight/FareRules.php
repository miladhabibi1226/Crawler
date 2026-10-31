<?php

namespace App\TravelServices\Suppliers\Kplus\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;

trait FareRules
{
    use FlightFareRules;
    protected function canBeDone()
    {
        if (!$this->findOrCreateSupplierSession()) {
            $this->addError('can not get session');
        }
    }

    protected function getContent()
    {
        // $this->fromFile=1;
        if ($this->fromFile) {
            $this->rawResult = ['http_code' => 200, 'body' => file_get_contents(storage_path('Samples/Kplus/FlightFareRules.json'))];
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
        $this->haveSupplierResponseError();
    }

    public function requestGenerator()
    {
        // $this->LugTheRequest = true;
        $this->LugTheResponse = 0;
        $depends = $this->seller->api->configs + $this->object->seller_info;
        lugInfo('FareRules data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'method' => 'POST',
            'url' =>  "{$depends['url']}/Air.svc/Rest/Json/GetFareRules",
            'apiMethod' => 'Fare Rules'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $result = $this->rawResult['decoded']['Result'] ?? [];
        if (empty($result)) {
            lugError('Empty fare rules result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError('Empty result');
        }
        foreach ($this->object->seller_info['segReferences'] as $segReference => $segments) {
            $items[$segReference]['group'] = [
                'airline' => $segments['airline'],
                'airports' => $segments['origin'] . ' - ' . $segments['destination']
            ];
        }
        foreach ($result as $fareRule) {
            $items[$fareRule['SegmentRef']]['details'][] = [
                'title' => $fareRule['Title'],
                'text' => $fareRule['Description'],
            ];
        }
        $this->items = array_values($items);
        lugInfo('Kplus FareRules extractor Items', ['Items' => $this->items]);
    }

    protected function getFields($depends)
    {
        return [
            "request" => [
                "ResultKeys" => $depends['searchKeys'],
                "TokenCode" => $this->latestSession
            ]
        ];
    }
}

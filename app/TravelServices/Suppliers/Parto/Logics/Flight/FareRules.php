<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Flight;

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
            $this->rawResult['decoded'] = json_decode(file_get_contents(storage_path('Samples/Parto/FlightRules.json')), true);
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
        $this->LugTheResponse = false;
        $this->LugTheRequest = false;
        $this->call = [
            'fields' => json_encode(["FareSourceCode" => $this->object->seller_info['FareSourceCode'], "SessionId" => $this->latestSession]),
            'url' =>  $this->cleanUrlSlash($this->seller->api->configs['url']) . "/Rest/Air/AirRules",
            'apiMethod' => 'Fare Rules'
        ];
        return $this->action();
    }

    protected function haveSupplierResponseError()
    {
        $rawError = $this->rawResult['decoded']['Error'];
        if (!empty($rawError)) {
            if ($rawError['Message'] === "Fare rules not available") {
                return false;
            }
            lugWSResError('Parto response contains error ', $this->lugInfo + ['supplierError: ' => $rawError ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            return $rawError['Message'] ?? $rawError;
        }
        return false;
    }

    public function extractor()
    {
        $this->items = [];
        $fareRulesResult = $this->rawResult['decoded']['FareRules'];

        $commons = [];
        foreach ($fareRulesResult as $object) {
            if (empty($object['Airline'])) {
                if (empty($object['RuleDetails'])) {
                    continue;
                }
                foreach ($object['RuleDetails'] as $rule) {
                    $commons[] = [
                        'title' => $rule['Category'],
                        'text' => $rule['Rules']
                    ];
                }
            }
        }

        foreach ($fareRulesResult as $key => $object) {
            $item = [];
            if (empty($object['Airline'])) {
                if (count($fareRulesResult) == 1) {
                    $this->items[$key]['group'] = [];
                    $this->items[$key]['details'] = $commons;
                }
                continue;
            }

            $item['group'] = [
                'airline' => $object['Airline'],
                'cities' => $object['CityPair'],
            ];
            foreach ($object['RuleDetails'] as $rule) {
                $item['details'][] = [
                    'title' => $rule['Category'],
                    'text' => $rule['Rules']
                ];
            }
            $item['details'] = array_merge($item['details'], $commons);
            $this->items[] = $item;
        }


        // lugInfo('Parto fareRules extractor Items', ['items' => $this->items]);
    }
}

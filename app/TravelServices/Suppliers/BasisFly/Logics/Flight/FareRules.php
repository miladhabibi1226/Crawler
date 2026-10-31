<?php

namespace App\TravelServices\Suppliers\BasisFly\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;
use Carbon\Carbon;

trait FareRules
{
    use FlightFareRules;

    public function canBedone()
    {
        $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        // $this->fromFile = true;
        if ($this->fromFile) {
            $this->rawResult['decoded'] = json_decode(file_get_contents(storage_path('Samples/BasisFly/FlightRules.json')), true);
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
        $flightGroup = $this->prepareFlightGroup();
        $auth = $this->seller->api->configs;
        $api = $this->cleanUrlSlash($auth['url']) . '/api-test/Flight_v2/AirRules';
        $this->call = [
            'fields' => json_encode([
                "ApiKey" => $auth['apiKey'],
                "SessionId" => $this->latestSession,
                "FlightId" => $this->object->seller_info['FlightId'],
                "FlightGroup" => $flightGroup,
            ]),
            'url' => $api,
            'timeout' => 15,
            'apiMethod' => 'Fare Rules'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $this->items = [];
        $fareRulesResults = $this->rawResult['decoded']['FlightRules'];
        foreach ($fareRulesResults as $fareRulesResult) {
            if (empty($fareRulesResult['Rule'])) continue;
            $item = [];
            $item['group'] = [
                'cities' => $fareRulesResult['From'] . ' - ' . $fareRulesResult['To']
            ];
            foreach ($fareRulesResult['Rule'] as $rule) {
                $item['details'][] = [
                    'title' => $rule['title'] ?? '',
                    'text' => $rule['text'] ?? '',
                ];
            }
            $this->items[] = $item;
        }
        // lugInfo('basisFly fareRules extractor Items', ['items' => $this->items]);
    }
}

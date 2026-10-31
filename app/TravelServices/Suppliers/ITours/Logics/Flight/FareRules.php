<?php

namespace App\TravelServices\Suppliers\ITours\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;

trait FareRules
{
    use FlightFareRules;
    protected function canBeDone()
    {
        $this->authorizeRequest();
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/ITours/FlightFareRules.json'));;
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
        $this->LugTheResponse = false;
        $this->LugTheRequest = false;
        $key = $this->object->seller_info['bookingRequestData']['key'];
        // lugInfo('FareRules data', ['key' => $key]);
        $this->call = [
            'fields' => '',
            'method' => 'GET',
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/Partners/Flight/V2/GetFlightRules?key=$key",
            'apiMethod' => 'Fare Rules'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $rawResults = $this->rawResult['decoded']['result'];
        $fareRules = $rawResults['flightRules'][0]['flightRule1'];
        if (count($rawResults['flightRules']) > 1) {
            lugError('MORE SEGMENTS???', [$rawResults]);
        }

        $item = [];
        $item['group'] = [
            'airline' => '-',
            'cities' => '-',
        ];

        foreach ($fareRules as $fareRule) {
            $item['details'][] = [
                'title' => $fareRule['ruleTitle'],
                'text' => $fareRule['ruleDescription']
            ];
        }
        $this->items[] = $item;

        // lugInfo('ITours FareRules extractor Items', ['Items' => $this->items]);
    }
};

<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;

trait FareRules
{
    use FlightFareRules;

    public function canBeDone()
    {
          return $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        // $this->fromFile = true;
        if ($this->fromFile) {
            $this->rawResult['decoded'] = json_decode(file_get_contents(storage_path('Samples/Sepehran/FlightRules.json')), true);
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        foreach ($this->rawResult as $key => $rawResult) {
            $this->rawResult[$key]['decoded'] = json_decode($rawResult['body'], true);
        }
        $resultErrors  = $this->haveSupplierResponseError();
        foreach ($resultErrors ?? [] as $key => $error) {
            if (empty($error)) {
                return true;
            }
            $errors[] = $error;
        }
        if (!empty($errors)) {
            $this->addError($errors);
        }
    }

    public function requestGenerator()
    {
        $this->LugTheResponse = false;
        $this->LugTheRequest = false;
        $auth = $this->seller->api->configs;
        $api = $this->cleanUrlSlash($auth['url']) . '/api/v2/flight/AirRule';
        $this->multi = true;

        $this->call[0] = [
            'fields' => json_encode([
                "ref_number" => [
                    $this->object->seller_info['refNumbers']['outbound'],
                ]
            ]),
            'url' => $api,
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'Fare Rules'
        ];

        if (!empty($this->object->seller_info['refNumbers']['return'])) {
            $this->call[1] = [
                'fields' => json_encode([
                    "ref_number" => [
                        $this->object->seller_info['refNumbers']['return'],
                    ]
                ]),
                'url' => $api,
                'headers' => ['Authorization:Bearer ' . $this->latestSession],
                'apiMethod' => 'Fare Rules'
            ];
        }

        return $this->action();
    }

    public function extractor()
    {
        foreach ($this->rawResult as $refNumber =>  $rawResult) {
            $fareRulesResult = $rawResult['decoded']['rules'];
            foreach ($fareRulesResult as $key => $object) {
                $item = [];
                $item['group'] = [
                    'cities' => $object['DepartureAirport'] . ' - ' . $object['ArrivalAirport']
                ];
                $details = [];
                foreach ($object['FareRules'] as $fareRule) {
                    $details['title'] = $fareRule['title'];
                    $text = [];
                    foreach ($object[$fareRule['title'] . "Array"] as $subSection) {
                        $text[] =  $subSection['title'] . '_' . $subSection['penalty'] ?? '';
                    }
                    $details['text'] = implode(". ", $text);
                }
                $item['details'][] = $details;
                $this->items[] = $item;
            }
        }
        // lugInfo('Sepehran fareRules extractor Items', ['items' => $this->items]);
    }
}

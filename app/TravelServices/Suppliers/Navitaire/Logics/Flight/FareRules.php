<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics\Flight;

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
        // $this->fromFile = $this->multi = 1;
        if ($this->fromFile) {
            $this->rawResult['MH5LfiB_Sjl_S0xTUE9XS1d_WEUzMH5_MH44fn5YITU'] = ['http_code' => 200, 'body' => file_get_contents(storage_path('Samples/Navitaire/FlightFareRules.json'))];
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        foreach ($this->rawResult as $key => $rawResult) {
            $this->rawResult[$key]['decoded'] = json_decode($rawResult['body'], true);
        }
        $this->haveSupplierResponseError();
    }

    public function requestGenerator()
    {
        ///> in Navitaire we have to acall fareRules for each passenger type seperately
        // $this->LugTheRequest = true;
        $this->multi = true;
        $this->LugTheResponse = 0;
        $depends = $this->seller->api->configs + $this->object->seller_info;
        lugInfo('FareRules data', [$depends]);
        foreach ($depends['bookingData'] as $data) {
            $this->call[$data['fareAvailabilityKey']] = [
                'fields' => '',
                'method' => 'GET',
                'url' =>  $this->cleanUrlSlash($depends['url']) . $this->getFareRulesUrl() ."{$data['fareAvailabilityKey']}",    
                'apiMethod' => 'Fare Rules'
            ];
        }
        return $this->action();
    }

    protected function getFareRulesUrl()
    {
        return '/api/jz/v1/FareRules?Farekey=';
    }

    public function extractor()
    {
        foreach ($this->object->seller_info['bookingData'] as $fare) {
            $fareRulesResult = $this->rawResult[$fare['fareAvailabilityKey']]['decoded']['data']['fareRulesByAvailabilityKey'] ?? [];
            if (empty($fareRulesResult)) {
                continue;
            }
            $item = [];
            $item['group'] = [
                'airline' => 'J9',
                'airports' => $fare['origin'] . ' - ' . $fare['destination']
            ];
            foreach ($fareRulesResult as $result) {
                $item['details'][] = [
                    'title' => 'Rules',
                    'text' => $result['content'] ?? '',
                ];
            }
            $this->items[] = $item;
        }
        // lugInfo('Navitaire FareRules extractor Items', ['Items' => $this->items]);
    }
}

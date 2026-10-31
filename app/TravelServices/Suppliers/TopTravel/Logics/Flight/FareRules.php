<?php

namespace App\TravelServices\Suppliers\TopTravel\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;
use Illuminate\Support\Str;

trait FareRules
{
    use FlightFareRules;

    public function canBedone()
    {
        return $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        // $this->fromFile = true;
        if ($this->fromFile) {
            $this->rawResult['decoded'] = json_decode(file_get_contents(storage_path('Samples/TopTravel/FlightFareRules.json')), true);
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
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->seller_info;
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($auth['url']) . '/satguruapiout/v1/flight/getFlightfarerule',
            'headers' => [
                'Authorization:Bearer ' . $this->latestSession,
                'Content-Type: application/json'
            ],
            'timeout' => 15,
            'apiMethod' => 'Fare Rules'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $result = $this->rawResult['decoded']['fareRuleResponseBean'] ?? [];
        if (empty($result)) {
            lugError('Empty fare rules result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            return $this->addError('response is invalid');
        }
        foreach ($this->getServiceInfo()['legs'] as $leg) {
            foreach ($leg['segments'] as $segment) {
                foreach ($result['headingWiseList'] as $rule) {
                    $items['group'] = [
                        'airline' => $segment['airline']['abb'],
                        'airports' => $segment['departure']['airport']['abb'] . ' - ' .  $segment['arrival']['airport']['abb']
                    ];
                    $penalityLists = $rule['penalityList'] ?? [];
                    foreach ($penalityLists as  $penalityList) {
                        foreach ($penalityList as $key => $pl) {
                            $text = $key . '_' . $pl;
                            $items['details'][] = [
                                'title' => $rule['heading'],
                                'text' => $rule['ruleDescription'] . '_' . $text,
                            ];
                        }
                    }
                }
                $this->items[] = $items;
                $items = [];
            }
        }
        // lugInfo('TopTravel FareRules extractor Items', ['Items' => $this->items]);
    }

    protected function getFields($depends)
    {
        return  [
            "agencyCode" => Str::before($depends['username'], ':'),
            "fareType" => "MINI",
            "flightOptionKey" => $depends['flightOptionKey'],
            "flightSearchKey" => $depends['flightSearchKey'],
            "noOfAdults" => $this->queryParams['adults'],
            "noOfChilds" => $this->queryParams['children'],
            "noOfInfants" => $this->queryParams['infants'],
            "tripType" =>  $this->queryParams['tripType']
        ];
    }
}

<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

use App\TravelServices\Logics\AfterSearch;
use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;

trait FareRules
{
    use AfterSearch, FlightFareRules{
        AfterSearch::needsSessionHandling insteadOf FlightFareRules;
    }
    protected $responseSample = 'Samples/CloudFares/FlightBrandedFare.json';
    protected function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs + $this->object->seller_info;
        lugInfo('FareRules data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'method' => 'POST',
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/b2b/v1/flight/get-fare-rule',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'Fare Rules'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $result = $this->rawResult['decoded']['data']['fareRule'] ?? [];
        if (empty($result)) {
            lugError('Empty fare rules result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            return $this->addError('response is invalid');
        }
        foreach ($this->getServiceInfo()['legs'] as $leg) {
            foreach ($leg['segments'] as $segment) {
                foreach ($result as $fareRules) {
                    foreach ($fareRules['rule'] as $rule) {
                        $items['group'] = [
                            'airline' => $segment['airline']['abb'],
                            'airports' => $segment['departure']['airport']['abb'] . ' - ' .  $segment['arrival']['airport']['abb']
                        ];
                        $items['details'][] = [
                            'title' => $rule['category'],
                            'text' => $rule['Text'],
                        ];
                    }
                }
                $this->items[] = $items;
            }
        }
        // lugInfo('CloudFares FareRules extractor Items', ['Items' => $this->items]);
    }


    protected function getFields($depends)
    {
        return [
            "trace_id" => $depends['trace_id'],
            "result_id" => $depends['result_id']
        ];
    }
}

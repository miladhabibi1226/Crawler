<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;

trait GetBrandedFares
{
    use BeforeBook;
    protected $responseSample = 'Samples/CloudFares/Validate.json';
    protected function canBeDone()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs + $this->object->seller_info;
        lugInfo('BrandedFares data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'method' => 'POST',
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/b2b/v1/flight/get-brand-fares',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'BrandedFares'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $this->items = [];
        $results  = $this->rawResult['decoded']['data'] ?? [];
        if (empty($results)) {
            lugError('Empty fare rules result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError('response is invalid');
            return false;
        }
        foreach ($results as $result) {
            foreach ($result['brand_legs'] as $branLeg) {
                $this->items['brand_result_id'][] = $branLeg['brand_id'];
            }
        }
        lugInfo('CloudFares BrandedFares extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->seller_info = array_merge($this->object->seller_info,  $this->items);
        $this->object->update();
    }

    protected function getFields($depends)
    {
        return [
            "trace_id" => $depends['trace_id'],
            "result_id" => $depends['result_id']
        ];
    }
}

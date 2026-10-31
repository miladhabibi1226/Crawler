<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

trait FareInformativePricing
{
    protected $method = 'Fare_InformativePricingWithoutPNR';

    public function requestGenerator()
    {
        $depends = $this->headerData() + $this->queryParams + ['include' => 'FareInformativePricing', 'rawTimes' => $this->object->seller_info['rawTimes'], 'legsServiceInfo' => $this->getServiceInfo()['legs']];
        lugInfo("Amadeus FareInformativePricing Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.start', $depends)->render(),
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'apiMethod' => 'FareInformativePricing'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $this->supplierSession = $this->saveSession();
        ///> for now we are not checking anything
        ///> just had to call this to be able to get the fare rules
        // lugInfo('Amadeus FareInformativePricing extractor Items', ['Items' => $this->items,'Items' => $this->supplierSession]);
    }

    protected function mapper()
    {
    }
}

<?php

namespace App\TravelServices\Suppliers\Quantum\Logics;

use App\TravelServices\Logics\Availabilities as MainAvailabilities;

trait Availabilities
{
    use MainAvailabilities;
    protected function getContent()
    {
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
        $depends = [
            'referenceId' => $this->object->seller_info['bufferReferenceId'],
            'type' => $this->travelService,
            'action' => "availabilities"
        ];
        // lugInfo('Blade data', [$depends]);
        $this->call = [
            'api' => 'v0/afterSearch',
            'host' => $auth['url'],
            'fields' => $depends,
            'apiMethod' => 'Availabilities'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $rawResult = $this->rawResult['decoded']['data'];
        $items = $this->availabilitiesModifier($rawResult);
        $this->items = [
            'serviceInfo' => $items['serviceInfo'],
            'priceInfo' => $items['priceInfo']
        ];
        if(!empty($items['sellerInfo'])){
            ///> package has sellerInfo
            $this->items['sellerInfo'] = $items['sellerInfo'];
        }

        // lugInfo("Quantum {$this->travelService} Availabilities extractor item", ['Items' => $this->items]);
    }

    protected function priceModifier($availabilities)
    {
        return $this->calculatePriceforAvailabilities([
            'priceInfo' => $availabilities,
        ]);
    }
}

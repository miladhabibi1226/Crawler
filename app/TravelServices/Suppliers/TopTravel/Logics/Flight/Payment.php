<?php

namespace App\TravelServices\Suppliers\TopTravel\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;
use Illuminate\Support\Str;

trait Payment
{
    use BeforeBook;
    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $this->call = [
            'fields' => json_encode($this->getFields($auth)),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/satguruapiout/v1/flight/getPaymentMode",
            'headers' => [
                'Authorization:Bearer ' . $this->latestSession,
                'Content-Type: application/json'
            ],
            'apiMethod' => 'Payment'
        ];
        return $this->action();
    }

    protected function getFields($auth)
    {
        return [
            "agencyCode" =>  Str::before($auth['username'], ':'),
            "userEmail" => Str::after($auth['username'], ':')
        ];
    }

    public function extractor()
    {
        $this->items = [];
        $validateResult = $this->rawResult['decoded'];
        if (empty($validateResult['paymentModeMap'])) {
            lugError('no paymentModeMap', [$this->rawResult]);
            $this->addError('response is invalid:');
            $this->addErrorToBook(trans('response is invalid:'));
            $this->failBook();
            return false;
        }
    }
}

<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Flight;

use App\TravelServices\Logics\BeforeBook as LogicsBeforeBook;

trait BeforeBook
{
    use LogicsBeforeBook;
    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = [
            'referenceId' => $this->object->seller_info['bufferReferenceId'],
            'type' => "flight",
            'action' => request()->action
        ];
        if (in_array(request()->action, ['addPNRElements', 'pricePNR', 'addPassengers'])) {
            $depends['passengersInfo'] = $this->object->passenger_info;
        }
        lugInfo('Request Data', [$depends]);
        $this->call = [
            'api' => 'v0/beforeBook',
            'host' => $this->cleanUrlSlash($auth['url']),
            'fields' => $depends,
            'apiMethod' => 'captcha'
        ];
        return $this->action();
    }

    protected function getCaptcha($result)
    {
        return $result['data']['captchaLink'] ?? null;
    }

    protected function extractor() {}
}

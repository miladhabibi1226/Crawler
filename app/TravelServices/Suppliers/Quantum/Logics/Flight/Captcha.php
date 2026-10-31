<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Flight;

use App\TravelServices\Logics\Flight\Captcha as FlightCaptcha;

trait Captcha
{
    use FlightCaptcha;
    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = [
            'referenceId' => $this->object->seller_info['bufferReferenceId'],
            'type' => "flight",
            'action' => "captcha"
        ];
        lugInfo('Request Data', [$depends]);
        $this->call = [
            'api' => 'v0/afterSearch',
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
}

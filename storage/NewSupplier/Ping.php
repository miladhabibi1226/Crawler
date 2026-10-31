<?php

namespace App\TravelServices\Suppliers\DummyName;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\DummyName\Logics\Common;

class Ping extends Remote
{
    use Common, LogicsPing;

    protected function getContent()
    {
        if ($this->requestGenerator() === false) {
            $this->addError("Request generator unsuccessful");
            return false;
        }

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs;
        lugInfo('Ping Data', [$depends]);
        $this->call = [
            'fields' => '',
            'url' =>  $depends['url']
        ];
        return $this->action();
    }
}

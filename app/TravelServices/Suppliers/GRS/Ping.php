<?php

namespace App\TravelServices\Suppliers\GRS;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\GRS\Logics\Common;

class Ping extends Remote
{
    use Common, LogicsPing;

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
        $depends = $this->seller->api->configs;
        lugInfo('Ping Data', [$depends]);
        $this->call = [
            'fields' => '',
            'method' => 'GET',
            'url' =>  $depends['url']. '/v1/profile'
        ];
        return $this->action();
    }
}

<?php

namespace App\TravelServices\Suppliers\TBO;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\TBO\Logics\Common;

class Ping extends Remote
{
    use Common, LogicsPing;


    protected function getContent()
    {

        if ($this->seller->api->configs['version'] === 'New') {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

            $error = $this->haveSupplierResponseErrorNew();
            if (!empty($error)) {
                $this->addError($error);
                return false;
            }
        } else {
            lugWarning('version of seller is old', [$this->seller]);
            return true;
        }
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs;
        lugInfo('Ping Data', [$depends]);
        $username = $this->seller->api->configs['username'];
        $password = $this->seller->api->configs['password'];
        $this->call = [
            'fields' => '',
            'method' => "GET",
            'headers' => [
                'Authorization: Basic ' . base64_encode("$username:$password")
            ],
            'url' =>  $depends['url'] . '/CountryList',
            'apiMethod' => 'Ping'
        ];
        return $this->action();
    }
}

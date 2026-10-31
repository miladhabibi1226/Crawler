<?php

namespace App\TravelServices\Suppliers\FlyErbil;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\FlyErbil\Logics\Common;

class Ping extends Remote
{
    use Common, LogicsPing;
    
    protected function getContent()
    {
        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
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
        $data = [
            'username' => $depends['username'],
            'password' => $depends['password'],
            'client_secret' => $depends['clientSecret'],
            'client_id' => $depends['clientId'],
            'grant_type' => 'password'
        ];
        $this->call = [
            'fields' => http_build_query($data),
            'url' =>  $depends['authenticationUrl'] . '/protocol/openid-connect/token',
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }
}

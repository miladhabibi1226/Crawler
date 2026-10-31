<?php

namespace App\TravelServices\Suppliers\Sepehr;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\Sepehr\Logics\Common;

class Ping extends Remote
{
    use Common, LogicsPing;

    protected function getContent()
    {
        if ($this->requestGenerator() === false) {
            $this->addError("Request generator unsuccessful");
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
            'Username' => $auth['username'],
            'Password' => hash('MD5', $auth['password'])
        ];
        lugInfo('Request Data', [$depends]);
        $this->call = [
            'fields' => json_encode($depends),
            'url' => $auth['url'] . "/api/Partners/Generic/V7/CurrentBalance",
            'apiMethod' => 'Ping'
        ];
        return $this->action();
    }
}

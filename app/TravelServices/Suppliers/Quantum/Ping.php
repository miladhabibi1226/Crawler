<?php

namespace App\TravelServices\Suppliers\Quantum;

use App\TravelServices\Internal;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\Quantum\Logics\Common;

class Ping extends Internal
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
        $auth = $this->seller->api->configs;
        $this->call = [
            'api' => "v0/ping",
            'fields' => ['bf' => true],
            'host' => $auth['url']
        ];
        return $this->action();
    }
}

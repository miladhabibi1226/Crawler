<?php

namespace App\TravelServices\Suppliers\Avtra;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\Avtra\Logics\Common;

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
        ///> Avtra has a ping request but we can't use it bc it doesn't confirm the auth/agent code

        $configs = $this->seller->api->configs;
        $depends = [
            'agentId' => $configs['agentId'],
            'country' => $configs['countryCode'],
            'currency' => $this->seller->currency->abb,
            'airline' => $configs['airlineCode']
        ];
        lugInfo('Ping Data', [$depends]);
        $this->call = [
            'fields' => view('Avtra.Ping', $depends)->render(),
            'url' =>  $this->cleanUrlSlash($configs['url']) . '/availability/lowfaresearch',
            'apiMethod' => 'Search'
        ];
        return $this->action();
    }

    protected function haveSupplierResponseError()
    {
        preg_match('/<Error ShortText="(.*?)" Code="(.*?)"\/>/ims', $this->rawResult['body'], $rawErrors);
        if (!empty($rawErrors)) {
            if (!in_array($rawErrors[2] ?? null, [531, 532])) {
                lugWSResError('Avtra response contain error', ['supplier-error: ' => $rawErrors ?? $this->rawResult['body']]);
                $this->supplierConversaion($rawErrors[1] ?? $rawErrors);
                return $rawErrors[1] ?? $rawErrors;
            }
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError('Avtra response is invalid', ['rawResult' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'Avtra response is invalid';
        }
        $this->supplierConversaion();
        return false;
    }
}

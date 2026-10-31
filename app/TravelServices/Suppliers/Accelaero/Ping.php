<?php

namespace App\TravelServices\Suppliers\Accelaero;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\Accelaero\Logics\Common;

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

        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//soap:Body')), true);
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs;
        lugInfo('Ping Data', [$depends]);
        $this->call = [
            'fields' => view('Accelaero.Ping', $depends)->render(),
            'url' =>  $depends['url'],
            'apiMethod' => 'Search'
        ];
        return $this->action();
    }

    protected function haveSupplierResponseError()
    {
        preg_match('/ns1:Error Code="4" ShortText="(.*?)"/ims', $this->rawResult['body'], $rawErrors);
        if (!empty($rawErrors)) {
            lugWSResError('Accelaero response contain error ', ['rawResult: ' => $this->rawResult['body']]);
            return $rawErrors[1] ?? 'Invalid Credentials';
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError('Accelaero response is invalid ', ['supplier-response: ' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'Invalid Response';
        }
        return false;
    }

    protected function  checkSupplierCondition()
    {
        $city = $this->getCityWithCountry($this->seller->api->configs['passengerDefaultCity']);
        if (empty($city['abb'])) {
            lugError('city abbreviation is empty', [$city]);
            $this->addError("city abbreviation is empty");
            return false;
        };
        return true;
    }
}

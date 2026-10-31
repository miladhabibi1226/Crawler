<?php

namespace App\TravelServices\Suppliers\Quantum\Logics;

trait Pay
{
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
            'api' => 'v0/pay',
            'host' => $auth['url'],
            'variables' => [$this->object->seller_info['bookingReferenceId']],
            'apiMethod' => 'Payment'
        ];
        return $this->action();
    }
}

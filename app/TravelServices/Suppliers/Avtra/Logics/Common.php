<?php

namespace App\TravelServices\Suppliers\Avtra\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Avtra';
        $this->defaultHeader = [
            'Accept:application/xml',
            'Content-Type:application/xml',
            'Authorization:' . $this->seller->api->configs['authorization'] ?? ''
        ];
    }

    protected function haveSupplierResponseError() ///> re-written in search
    {
        preg_match('/<Error ShortText="(.*?)" Code=(.*?)>/ims', $this->rawResult['body'], $rawErrors);
        if (!empty($rawErrors)) {
            lugWSResError("Avtra response contain error ({$this->seller->alias})", ['supplier-error: ' => $rawErrors ?? $this->rawResult['body']]);
            $this->supplierConversaion($rawErrors[1] ?? $rawErrors);
            return $rawErrors[1] ?? $rawErrors;
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("Avtra response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            $this->supplierConversaion("invalid response status: " . $this->rawResult['http_code']);
            return "Avtra response is invalid";
        }
        $this->supplierConversaion();
        return false;
    }
}

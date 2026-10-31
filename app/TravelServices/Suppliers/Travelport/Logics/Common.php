<?php

namespace App\TravelServices\Suppliers\Travelport\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;
    protected $sessionLifeTime;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Travelport';
        $this->dataReference = types('supplierDataReference', 'Travelport');
        $this->defaultHeader = [
            "Content-Type: text/xml;charset=UTF-8",
            "Accept: gzip,deflate",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: \"\"",
            "Authorization: Basic " . $this->prepareAuthorization($this->seller->api->configs),
        ];
        $this->sessionLifeTime = 20;
    }

    protected function prepareAuthorization($data)
    {
        $CREDENTIALS = $data['username'] . ':' . $data['password'];
        $auth = base64_encode("$CREDENTIALS");
        return $auth;
    }

    public function price()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Travelport-Flight) -> Price', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Travelport-Flight) -> Price', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }
        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Travelport) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Travelport-Flight) -> Price', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        preg_match('/<faultstring>(.*?)<\/faultstring>/', $this->rawResult['body'], $rawErrors);
        if (!empty($rawErrors)) {
            lugWSResError("Travelport response contains error ({$this->seller->alias})", ['supplierError: ' => $rawErrors[1] ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawErrors[1]);
            return $rawErrors[1];
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("Travelport response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'Travelport response is invalid';
        }
        $this->supplierConversaion();
        return false;
    }
}

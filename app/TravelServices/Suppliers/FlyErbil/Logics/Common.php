<?php

namespace App\TravelServices\Suppliers\FlyErbil\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'FlyErbil';
        $this->sessionLifeTime = 240;
        $this->defaultHeader = [];
    }

    public function signIn()
    {
        lugInfo('Get Content: ' . $this->seller->alias . ' (FlyErbil-Flight) -> SignIn', ['Data' => $this->object->toArray()]);
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (FlyErbil-Flight) -> SignIn', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (FlyErbil-Flight) -> SignIn', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper (FlyErbilFlight) SignIn', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (FlyErbil-Flight) -> SignIn');
        return $this->done();
    }

    public function priceQuote()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (FlyErbil-' . $this->travelService . ') -> Price Quote', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Get Content ' . $this->seller->alias . ' (FlyErbil-' . $this->travelService . ') -> Price Quote', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Extractor ' . $this->seller->alias . ' (FlyErbil-' . $this->travelService . ') -> Price Quote', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Mapper ' . $this->seller->alias . ' (FlyErbil-' . $this->travelService . ') -> Price Quote', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (FlyErbil-' . $this->travelService . ') -> Price Quote');
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        preg_match('/<ota:OTA_ErrorRS[^s][^>]+>/ims', $this->rawResult['body'], $rawErrors);
        if (!empty($rawErrors)) {
            lugWSResError("FlyErbil response contain error ({$this->seller->alias})", ['supplier-error: ' => $rawErrors ?? $this->rawResult['body'], 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawErrors);
            return $rawErrors;
        }
        preg_match('/<ota:Error[^s][^>]+>(.*?)<\/ota:Error>/ims', $this->rawResult['body'], $rawErrors);
        if (!empty($rawErrors)) {
            lugWSResError("FlyErbil response contain error ({$this->seller->alias})", ['supplier-error: ' => $rawErrors ?? $this->rawResult['body'], 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawErrors[1] ?? $rawErrors[0]);
            return $rawErrors;
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("FlyErbil response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'FlyErbil response is invalid';
        }
        $this->supplierConversaion();
        return false;
    }
}

<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;
    protected $sessionLifeTime;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Sepehran';
        $this->defaultHeader = ['Content-Type:application/json'];
        $this->dataReference = types('supplierDataReference', 'Sepehran');
        $this->supplierSearchValidity = 15;
        $this->sessionLifeTime = 10;
    }

    protected function haveSupplierResponseError()
    {

        if ($this->multi) {
            foreach ($this->rawResult as $key => $rawResult) {
                $ch = $this->checkResult($rawResult);
                if (!empty($ch)) {
                    $checked[$key] = $ch;
                }
            }
        } else {
            $checked = $this->checkResult($this->rawResult);
        }
        $this->supplierConversaion($checked ?? null);
        return $checked ?? [];
    }


    public function price()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Sepehran-Flight) - Price)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Sepehran-Flight) -> Price', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Sepehran-Flight) -> Price', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Sepehran-Flight) -> Price', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function confirm()
    {

        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Sepehran-Flight) - confirm)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Sepehran-Flight) -> confirm', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Sepehran-Flight) -> confirm', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Sepehran-Flight) -> confirm', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Sepehran-Flight) -> confirm', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    protected function checkResult($rawResult)
    {
        if (strtolower($rawResult['decoded']['status'] ?? 'success') != 'success') {
            $rawErrors = array_column($rawResult['decoded']['err'] ?? [], "msg");
            $rawError = implode(".", $rawErrors);
            lugWSResError("Sepehran response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError, 'supplier response: ' => $rawResult['body']]);
            return $rawError ?? 'Sepehran response contains error';
        }

        if ($rawResult['http_code'] != 200) {
            lugWSResError("Sepehran response is invalid ({$this->seller->alias})", ['rawResult' => $rawResult]);
            return 'Sepehran response is invalid';
        }
        return false;
    }

    public function checkCapacity()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Sepehran-Flight) -> checkCapacity', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Sepehran-Flight) -> checkCapacity', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Sepehran-Flight) -> checkCapacity', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Sepehran-Flight) -> checkCapacity', ['Data' => $this->object->toArray()]);
        return $this->done();
    }
}

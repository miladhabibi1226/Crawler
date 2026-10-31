<?php

namespace App\TravelServices\Suppliers\Kplus\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Kplus';
        $this->defaultHeader = ['Content-Type:application/json'];
        $this->dataReference = types('supplierDataReference', 'Kplus');
        $this->supplierSearchValidity = 15;
    }

    public function getBrandedFares()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Kplus) - Get Branded Fares)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Kplus) - Get Branded Fares)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Kplus) - Get Branded Fares)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Kplus) - Get Branded Fares)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Kplus) - Get Branded Fares)', ['object' => $this->object->toArray()]);
        return $this->done();
    }

    public function revalidate()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Kplus) - Validate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Kplus) - Validate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Kplus) - Validate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Kplus) - Validate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Kplus) - Validate)', ['object' => $this->object->toArray()]);
        return $this->done();
    }

    protected function haveSupplierResponseError($openReturnresult = null)
    {
        $rawResult = empty($openReturnresult) ? $this->rawResult : $openReturnresult;
        $rawError = $rawResult['decoded']['ErrorMessage'] ?? null;
        if (!empty($rawError)) {
            lugWSResError("Kplus response contains error ({$this->seller->alias})", ['supplierError' => $rawError ?? '', 'supplier response: ' => $rawResult['body']]);
            $this->supplierConversaion($rawError["Message"] ?? $rawError);
            return $rawError["Message"] ?? $rawError;
        }
        if ($rawResult['http_code'] != 200) {
            lugWSResError("Kplus response is invalid ({$this->seller->alias})", ['rawResult' => $rawResult]);
            $this->supplierConversaion('invalid response status: ' . $rawResult['http_code']);
            return 'Kplus response is invalid';
        }
        return false;
    }
}

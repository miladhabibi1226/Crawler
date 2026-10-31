<?php

namespace App\TravelServices\Suppliers\Sepehr\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;
    //
    protected $sessionLifeTime;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Sepehr';
        $this->dataReference = types('supplierDataReference', 'Sepehr');
        $this->defaultHeader = ['Content-Type:application/json'];
        $this->sessionLifeTime = 60;
    }

    protected function haveSupplierResponseError()
    {
        if ($this->multi) {
            foreach ($this->rawResult as $rawResult) {
                $ch = $this->checkResult($rawResult);
                if (!empty($ch)) {
                    $checked[] = $ch;
                }
            }
        } else {
            $checked = $this->checkResult($this->rawResult);
        }
        $this->supplierConversaion($checked ?? null);
        return $checked ?? [];
    }

    protected function checkResult($rawResult)
    {
        $rawError = $rawResult['decoded']['ErrorMessage'] ?? null;
        if (!empty($rawError)) {
            if ($this->action != 'search') {
                lugWSResError("Sepehr response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError, 'supplier response: ' => $rawResult['body']]);
            }
            return $rawError;
        }

        $rawWarning = $rawResult['decoded']['Warnings'] ?? null;
        if (!empty($rawWarning)) {
            if ($this->action != 'search') {
                lugWSResError("Sepehr response contains error ({$this->seller->alias})", ['supplierError: ' => $rawWarning[0]["ShortText"] ?? $rawResult, 'supplier response: ' => $rawResult['body']]);
            }
            return $rawWarning;
        }

        if (!in_array($rawResult['http_code'], [200, 504])) {
            if ($this->action != 'search') {
                lugWSResError("Sepehr response is invalid ({$this->seller->alias})", ['rawResult' => $rawResult]);
            }
            return 'invalid response status';
        }
        return false;
    }

    public function revalidate()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Sepehr-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Sepehr-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Sepehr-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Sepehr-Flight) -> Revalidate', ['Data' => $this->object->toArray()]);
        return $this->done();
    }
}

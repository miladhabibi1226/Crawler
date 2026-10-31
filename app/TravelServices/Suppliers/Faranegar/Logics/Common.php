<?php

namespace App\TravelServices\Suppliers\Faranegar\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Faranegar';
        $this->dataReference = types('supplierDataReference', 'Faranegar');
        $this->supplierSearchValidity = 15;
        $this->defaultHeader = ['Content-Type:application/json'];
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
        $rawError = $rawResult['decoded']['Error'] ?? null;
        if (!empty($rawError)) {
            $errorMessage = $rawError["Message"] ?? "";
            lugWSResError("Faranegar response contains error ({$this->seller->alias})", ['supplierError: ' => $errorMessage ?? ($rawError ?? $this->rawResult)]);
            return  $errorMessage ?? "Undetected error";
        }
        if ($rawResult['http_code'] != 200) {
            // lugWSResError("Faranegar response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            return 'Faranegar response is invalid';
        }
        return false;
    }
}

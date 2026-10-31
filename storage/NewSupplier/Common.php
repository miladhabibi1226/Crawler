<?php

namespace App\TravelServices\Suppliers\DummyName\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'DummyName';
        $this->dataReference = types('supplierDataReference', 'DummyName');
        $this->supplierSearchValidity = 15;
    }

    protected function haveSupplierResponseError()
    {
        return false;
    }
}

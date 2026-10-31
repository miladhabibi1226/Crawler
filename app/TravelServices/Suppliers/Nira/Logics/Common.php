<?php

namespace App\TravelServices\Suppliers\Nira\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;
use MatinUtils\NodeTracker\Models\Airline;
use MatinUtils\NodeTracker\Models\SupplierService;

trait Common
{
    use LogicsCommon, Errors;
    protected $Airline;
    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Nira';
        $this->dataReference = types('supplierDataReference', 'Nira');
        $this->supplierSearchValidity = 15;
        $this->defaultHeader = ['Content-Type:application/json'];
        static $airline;
        if (empty($airline[$this->seller->supplier_service_id])) {
            $airlineId = SupplierService::select('entity_id')->find($this->seller->supplier_service_id)->entity_id;
            $airline[$this->seller->supplier_service_id] = $this->realAirlineCode(Airline::where('id', $airlineId)->pluck('abb')->first());
        }
        $this->Airline = $airline[$this->seller->supplier_service_id];
    }

    protected function realAirlineCode($codeInDB)
    {
        ///> this is not very good looking but i can't think abt anything else rn
        return ['ISQ' => 'IS', 'PYA' => 'PY'][$codeInDB] ?? $codeInDB;
    }
}

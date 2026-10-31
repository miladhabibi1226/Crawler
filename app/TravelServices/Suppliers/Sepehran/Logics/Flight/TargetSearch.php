<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics\Flight;

use App\TravelServices\Logics\Flight\TargetSearch as CommonTargetSearch;

trait TargetSearch
{
    use CommonTargetSearch, CustomSearch{
        CommonTargetSearch::priceInfo insteadOf CustomSearch;
        CommonTargetSearch::chekCapacity insteadof CustomSearch;
    }

    public function canBeDone()
    {
        if (!$this->findOrCreateSupplierSession()) {
            return $this->addError('can not get session');
        }
    }
}

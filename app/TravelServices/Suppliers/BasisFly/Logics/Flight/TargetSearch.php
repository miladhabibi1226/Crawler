<?php

namespace App\TravelServices\Suppliers\BasisFly\Logics\Flight;

use App\TravelServices\Logics\Flight\TargetSearch as CommonTargetSearch;
use App\TravelServices\Handler;

trait TargetSearch
{
    use CommonTargetSearch, CustomSearch {
        CommonTargetSearch::priceInfo insteadof CustomSearch;
        CommonTargetSearch::chekCapacity insteadof CustomSearch;
    }

    public function canBeDone()
    {
        $signIn = Handler::signIn($this->seller);
        if (!$signIn['status'] ?? false) {
            return false;
        }
        $this->supplierSession = $signIn['supplierSession'];
        $this->latestSession = $this->supplierSession->session;
    }
}

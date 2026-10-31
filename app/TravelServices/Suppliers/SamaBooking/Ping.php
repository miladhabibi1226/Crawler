<?php

namespace App\TravelServices\Suppliers\SamaBooking;

use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Remote;
use App\TravelServices\Suppliers\IRIX\Logics\Common;
use App\TravelServices\Suppliers\IRIX\Logics\Hotel\SignIn;

class Ping extends Remote
{
    use SignIn, Common, LogicsPing {
        LogicsPing::canBeDone insteadOf Common;
    }

    protected function haveSupplierResponseError()
    {
        $rawError = $this->rawResult['decoded']['error'] ?? false;
        if ($rawError) {
            lugWSResError("SamaBooking response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $this->rawResult['decoded'], 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError ?? $rawError);
            return $this->rawResult['decoded']['message'] ?? 'Error Happen';
        }
        $this->supplierConversaion();
        return false;
    }
}

<?php

namespace App\TravelServices\Logics;

trait SignOut
{
    protected function done()
    {
        foreach ($this->object as $supplierSession) {
            $supplierSession->delete();
        }
        return ['status' => true];
    }
}

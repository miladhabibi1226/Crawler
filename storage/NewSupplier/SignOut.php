<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\DummyTravelService;

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
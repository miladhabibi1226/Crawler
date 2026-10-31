<?php

namespace App\TravelServices\Suppliers\Parto;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\Parto\Logics\Common;
use App\TravelServices\Suppliers\Parto\Logics\Flight\SignIn;

class Ping extends Remote
{
    use SignIn, Common, LogicsPing;

    public function done()
    {
        return ['status' => true];
    }
}

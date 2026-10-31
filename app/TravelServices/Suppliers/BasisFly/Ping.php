<?php

namespace App\TravelServices\Suppliers\BasisFly;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\BasisFly\Logics\Common;
use App\TravelServices\Suppliers\BasisFly\Logics\Flight\SignIn;


class Ping extends Remote
{
    use SignIn, Common, LogicsPing;

    public function done()
    {
        return ['status' => true];
    }
}

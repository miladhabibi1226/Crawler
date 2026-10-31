<?php

namespace App\TravelServices\Suppliers\MystiFly;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\MystiFly\Logics\Common;
use App\TravelServices\Suppliers\MystiFly\Logics\Flight\SignIn;

class Ping extends Remote
{
    use SignIn, Common, LogicsPing;

    public function done()
    {
        return ['status' => true];
    }
}

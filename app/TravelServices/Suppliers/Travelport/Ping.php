<?php

namespace App\TravelServices\Suppliers\Travelport;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\Travelport\Logics\Common;



class Ping extends Remote
{
    use Common, LogicsPing;

    public function getContent()
    {
        return true;
    }

    public function done()
    {
        return ['status' => true];
    }
}

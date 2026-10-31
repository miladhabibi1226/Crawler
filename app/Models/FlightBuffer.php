<?php

namespace App\Models;

use MatinUtils\NodeTracker\Models\FlightBuffer as NodeTrackerFlightBuffer;
class FlightBuffer extends NodeTrackerFlightBuffer
{
    public function getPassengerInfoAttribute()
    {
        return request('passengersInfo', []);
    }
}

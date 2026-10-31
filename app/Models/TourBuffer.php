<?php

namespace App\Models;

use MatinUtils\NodeTracker\Models\TourBuffer as NodeTrackerTourBuffer;

class TourBuffer extends NodeTrackerTourBuffer
{
    public function getPassengerInfoAttribute()
    {
        return request('passengersInfo', []);
    }
}

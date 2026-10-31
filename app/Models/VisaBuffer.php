<?php

namespace App\Models;

use MatinUtils\NodeTracker\Models\VisaBuffer as NodeTrackerVisaBuffer;

class VisaBuffer extends NodeTrackerVisaBuffer
{
    public function getPassengerInfoAttribute()
    {
        return request('passengersInfo', []);
    }
}

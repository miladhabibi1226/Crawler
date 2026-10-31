<?php

namespace App\Models;

use MatinUtils\NodeTracker\Models\EventBuffer as NodeTrackerEventBuffer;

class EventBuffer extends NodeTrackerEventBuffer
{
    public function getPassengerInfoAttribute()
    {
        return request('passengersInfo', []);
    }
}

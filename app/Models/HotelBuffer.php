<?php

namespace App\Models;

use MatinUtils\NodeTracker\Models\HotelBuffer as NodeTrackerHotelBuffer;

class HotelBuffer extends NodeTrackerHotelBuffer
{
    public function getPassengerInfoAttribute()
    {
        return request('passengersInfo', []);
    }
}

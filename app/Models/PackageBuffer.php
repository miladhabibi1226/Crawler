<?php

namespace App\Models;

use MatinUtils\NodeTracker\Models\PackageBuffer as NodeTrackerPackageBuffer;

class PackageBuffer extends NodeTrackerPackageBuffer
{
    public function getPassengerInfoAttribute()
    {
        return request('passengersInfo', []);
    }
}

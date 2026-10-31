<?php

namespace App\Models;

use MatinUtils\NodeTracker\Models\Hotel as NodeTrackerHotel;

class Hotel extends NodeTrackerHotel
{
    protected $fillable = ['city_id', 'title'];
}

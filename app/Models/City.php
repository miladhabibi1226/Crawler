<?php

namespace App\Models;

use MatinUtils\NodeTracker\Models\City as NodeTrackerCity;

class City extends NodeTrackerCity
{
    protected $hidden = ['id', 'created_at', 'updated_at', 'country_id', 'deleted_at', 'priority', 'status'];
}

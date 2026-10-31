<?php

namespace App\Models;

use MatinUtils\NodeTracker\Models\Landmark as NodeTrackerLandmark;

class Landmark extends NodeTrackerLandmark
{
    protected $hidden = ['reference_id', 'id', 'created_at', 'updated_at', 'deleted_at'];
}

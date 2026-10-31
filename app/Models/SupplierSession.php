<?php

namespace App\Models;

use MatinUtils\NodeTracker\Models\SupplierSession as NodeTrackerSupplierSession;

class SupplierSession extends NodeTrackerSupplierSession
{
    protected $fillable = ['session', 'expire_on', 'seller_id', 'searcher_identity', 'extras'];
    
}

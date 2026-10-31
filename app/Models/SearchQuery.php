<?php

namespace App\Models;

use MatinUtils\NodeTracker\Models\SearchQuery as NodeTrackerSearchQuery;
class SearchQuery extends NodeTrackerSearchQuery
{
    protected $fillable = ['session_id', 'buyer_group_id', 'query_params', 'type'];

    public function hotels()
    {
        return $this->hasMany(HotelBuffer::class, 'search_query_id', 'id');
    }

    public function flights()
    {
        return $this->hasMany(FlightBuffer::class, 'search_query_id', 'id');
    }

    public function packages()
    {
        return $this->hasMany(PackageBuffer::class, 'search_query_id', 'id');
    }

    public function visas()
    {
        return $this->hasMany(VisaBuffer::class, 'search_query_id', 'id');
    }
}

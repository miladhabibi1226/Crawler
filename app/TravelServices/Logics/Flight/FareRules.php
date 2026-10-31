<?php

namespace App\TravelServices\Logics\Flight;

use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\ServiceInfo;

trait FareRules
{
    protected function mapper()
    {
        ServiceInfo::insert([
            'reference_id' => $this->object->service_info,
            'type' => 'fareRules',
            'data' => json_encode($this->items),
            'created_at' => Carbon::now()
        ]);
        $this->object->seller_info = $this->sellerInfoData();
        $this->object->update();
        if ($this->needsSessionHandling()) {
            $this->handleSignOut();
        }
    }

    protected function sellerInfoData() ///> rewritten in MystiFly
    {
        $sellerinfo = $this->object->seller_info;
        $sellerinfo['afters']['fareRules'] = true;
        return $sellerinfo;
    }

    protected function needsSessionHandling()
    {
        return in_array($this->supplierName, ['Amadeus']);
    }
}

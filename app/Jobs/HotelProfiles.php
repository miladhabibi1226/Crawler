<?php

namespace App\Jobs;

use App\Jobs\Job;

class HotelProfiles extends Job
{
    protected $pid, $supplierBrand, $nodeId, $hotels, $sellerDataReference, $isDomestic, $sellerId, $jobConnection;
    public function __construct(string $pid, $jobConnection, $supplierBrand, $nodeId, array $hotels, string $sellerDataReference, $sellerId, $isDomestic = null)
    {
        // lugWarning(__METHOD__, [$hotels, $sellerDataReference, $isDomestic, $sellerId]);
        $this->pid = $jobConnection == 'sync' ? $pid : 'chunkH-' . uniqid();
        $this->jobConnection = $jobConnection;
        $this->supplierBrand = $supplierBrand;
        $this->nodeId = $nodeId;
        $this->hotels = $hotels;
        $this->sellerDataReference = $sellerDataReference;
        $this->isDomestic = $isDomestic;
        $this->sellerId = $sellerId;
    }

    public function operate()
    {
        foreach (array_chunk($this->hotels ?? [], 40) as $hotels) {
            $class = "App\Jobs\ChunkHotels\\$this->supplierBrand";
            dispatch(new $class($this->pid, $this->nodeId, $hotels, $this->sellerDataReference, $this->sellerId, $this->isDomestic))->onQueue('supplierData')->onConnection($this->jobConnection);
        }
    }
}

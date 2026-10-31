<?php

namespace App\Jobs;

use App\Jobs\Job;
use MatinUtils\NodeTracker\Models\HotelSupplier;

class HotelsFrequency extends Job
{
    protected $hotels = [], $sellerDataReference, $pid;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $pid, array $hotels, string $sellerDataReference)
    {
        // lugWarning(__METHOD__, [$hotels, $sellerDataReference]);
        $this->pid = $pid;
        $this->hotels = $hotels;
        $this->sellerDataReference = $sellerDataReference;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function operate()
    {
        app('log-system')->setPid($this->pid);
        $hotelSuppliers = HotelSupplier::whereDataReference(types('supplierDataReference', $this->sellerDataReference))->whereIn('supplier_key', $this->hotels)->get();
        $existingHotels = [];
        foreach ($hotelSuppliers as $hotelSupplier) {
            ///> can not use $hotelSupplier->update(); because the table doesn't have id
            HotelSupplier::whereDataReference(types('supplierDataReference', $this->sellerDataReference))->where('supplier_key', $hotelSupplier->supplier_key)->update(['frequency' => $hotelSupplier->frequency  + 1]);
            $existingHotels[] = $hotelSupplier->supplier_key;
        }
        foreach ($this->hotels as $code) {
            if (!in_array($code, $existingHotels)) {
                $insertable[] = ['data_reference' => types('supplierDataReference', $this->sellerDataReference), 'supplier_key' => $code, 'frequency' => 1];
            }
        }
        HotelSupplier::insert($insertable ?? []);
        // lugWarning("hotels frequency job: $this->sellerDataReference", ['input' => $this->hotels, 'last updated HotelSuppliers' => $hotelSuppliers ?? null, 'last inserted HotelSuppliers' => $insertable ?? null]);
    }
}

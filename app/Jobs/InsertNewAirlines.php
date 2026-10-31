<?php

namespace App\Jobs;

use App\Jobs\Job;
use MatinUtils\NodeTracker\Models\AirLineSupplier;

class InsertNewAirlines extends Job
{
    protected $newAirlines = [], $pid;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $pid, array $newAirlines)
    {
        // lugWarning(__METHOD__, [$newAirlines]);
        $this->pid = $pid;
        $this->newAirlines = $newAirlines;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function operate()
    {
        app('log-system')->setPid($this->pid);

        foreach ($this->newAirlines as $airlineSupplier) {
            $as = AirLineSupplier::where('supplier_key', $airlineSupplier['supplier_key'])->where('data_reference', $airlineSupplier['data_reference'])->first();
            if (empty($as)) {
                AirLineSupplier::insert($airlineSupplier + ['frequency' => 1]);
                $new[] = $airlineSupplier;
            }
        }

        if (count($new ?? []) > 0) {
            lugInfo('inserted ' . count($new ?? []) . ' new airline_suppliers', [$new ?? []]);
        }
    }
}

<?php

namespace App\Jobs;

use App\Jobs\Job;
use MatinUtils\NodeTracker\Models\Airline;
use MatinUtils\NodeTracker\Models\AirLineSupplier;

class AirlinesFrequency extends Job
{
    protected $airlines = [], $sellerDataReference, $pid;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $pid, array $airlines, string $sellerDataReference)
    {
        // lugWarning(__METHOD__, [$airlines, $sellerDataReference]);
        $this->pid = $pid;
        $this->airlines = $airlines;
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

        foreach ($this->airlines as $code => $numberOfResults) {
            $airline = null;

            if ($this->sellerDataReference == 'Quantum') {
                $airline = Airline::find($code);
            } else {
                $AirlineSupplier = AirLineSupplier::whereDataReference(types('supplierDataReference', $this->sellerDataReference))->whereSupplierKey($code)->first();
                if (!empty($AirlineSupplier)) {
                    $AirlineSupplier->frequency = $AirlineSupplier->frequency + $numberOfResults;
                    $AirlineSupplier->update();
                    $airline = $AirlineSupplier->airline;
                }
            }

            if (!empty($airline)) {
                $airline->frequency = $airline->frequency + $numberOfResults;
                $airline->update();
            }
        }

        // lugWarning("Airlines frequency job: $this->sellerDataReference", ['input' => $this->airlines, 'last updated airlineSupplier' => $AirlineSupplier ?? null, 'last updated airline' => $airline ?? null]);
    }
}

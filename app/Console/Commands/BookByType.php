<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\Booking;

class BookByType extends Command
{
    protected $signature = 'bookByType {days?} {--travelService=} {--nodeId=}';
    protected $time;

    public function handle()
    {
        $this->time = Carbon::today();
        if (!empty($this->argument('days'))) {
            $this->time = $this->time->subDays($this->argument('days'));
        }
        $data = $this->a();
        dd($data, "Calculated from " . $this->time->setTimezone('Asia/Tehran')->format('y-M-d H:i:s') . " (Tehran time)");
    }

    protected function a()
    {
        foreach (allNodeIds() as $nodeId) {
            if (!empty($this->option('nodeId')) && $this->option('nodeId') != $nodeId) continue;
            switchNode($nodeId);
            $nodeData = Booking::where('created_at', '>', $this->time)
                ->when(!empty($this->option('travelService')), fn ($q) => $q->where('travel_service', types('travelService', $this->option('travelService'))))
                ->pluck('travel_service', 'created_at');
            $details = [];
            foreach ($nodeData as $created_at => $travelService) {
                $key = Carbon::create($created_at)->setTimezone('Asia/Tehran')->format('Y-m');
                $details[types('travelService', $travelService)][$key] = ($details[types('travelService', $travelService)][$key] ?? 0) + 1;
            }
            if (!empty(count($nodeData))) {
                $data[$nodeId] = $details;
            }
        }
        return $data ?? [];
    }
}

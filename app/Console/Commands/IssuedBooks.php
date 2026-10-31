<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\Booking;

class IssuedBooks extends Command
{
    protected $signature = 'issued {days?}';
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
            switchNode($nodeId);
            $nodeData = Booking::where('created_at', '>', $this->time)
                ->whereIn('status', [
                    types('bookingStatus', 'on_hold'),
                    types('bookingStatus', 'issued'),
                    types('bookingStatus', 'waiting_issue')
                ])->pluck('created_at');
            foreach ($nodeData as $key => $value) {
                $key = $value->setTimezone('Asia/Tehran')->format('H');
                $data[$nodeId]['details'][$key] = ($data[$nodeId]['details'][$key] ?? 0) + 1;
            }
            if (!empty(count($nodeData))) {
                $data[$nodeId]['total'] = count($nodeData);
            }
        }
        return $data ?? [];
    }
}

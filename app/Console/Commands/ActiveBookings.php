<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\Booking;

class ActiveBookings extends Command
{
    protected $signature = 'actives';

    public function handle()
    {
        dd($this->a(), Carbon::now()->format('H:i:s'));
    }

    protected function a()
    {
        foreach (allNodeIds() as $nodeId) {
            switchNode($nodeId);
            $b[$nodeId] = Booking::where('created_at', '>', Carbon::now()->subHour())
                ->whereIn('status', [
                    types('bookingStatus', 'on_hold'),
                    types('bookingStatus', 'validated'),
                    types('bookingStatus', 'waiting_issue')
                ])
                ->pluck('status', 'created_at')->toArray();
        }
        dump(Carbon::now()->format('H:i:s'));
        foreach ($b as $key => $value) {
            foreach ($value as $i => $v) {
                $b[$key][$i] = types('bookingStatus', $v);
            }
        }
        return $b;
    }
}

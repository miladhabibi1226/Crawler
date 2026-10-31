<?php

namespace App\Console\Commands;

use App\TravelServices\Handler;
use Illuminate\Console\Command;

class BeforeBook extends Command
{
    protected $signature = 'beforeBook {action : action} {nodeId : Node Id} {booking : BookingReference} {--p|pid= : PID} ';

    protected $description = 'Command description';

    public function handle()
    {
        //////////////> only for developement
        app('log-system')->setpid($this->option('pid'));
        switchNode($this->argument('nodeId'));
        request()->merge(['action' => $this->argument('action'), 'travelService' => 'flight']);
        Handler::beforeBook($this->argument('booking'));
        app('log-system')->send();
    }
}

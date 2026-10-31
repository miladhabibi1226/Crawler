<?php

namespace App\Console\Commands;

use App\MSC\ProcessManager;
use Illuminate\Console\Command;

class CrawlerRun extends Command
{
    protected $signature = 'crawler:run';

    protected $description = 'run Crawler';

    public function handle()
    {
        $clientPort = config('processManager.clientPort', 'client');
        $workerPort = config('processManager.workerPort', 'worker');
        $workerCount = config('processManager.numOfProcess', 20);
        return (new ProcessManager())->run($clientPort, $workerPort, $workerCount);
    }
}

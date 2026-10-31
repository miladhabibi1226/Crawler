<?php

namespace App\Console\Commands\Microservice;

use Illuminate\Console\Command;

class Update extends Command
{
    protected $signature = 'microservice:update';

    protected $description = '';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->gitPull();
        app('log-system')->send();
    }

    protected function gitPull()
    {
        $this->info("Executing: git pull");
        echo shell_exec('git pull');
    }
}

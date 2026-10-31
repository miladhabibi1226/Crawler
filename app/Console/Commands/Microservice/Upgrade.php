<?php

namespace App\Console\Commands\Microservice;

use Illuminate\Console\Command;

class Upgrade extends Command
{
    protected $signature = 'microservice:upgrade';

    protected $description = '';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->gitPull();
        $this->composerUpdate();
        app('log-system')->send();
    }

    protected function gitPull()
    {
        $this->info("Executing: git pull");
        echo shell_exec('git pull');
    }

    protected function composerUpdate()
    {
        $this->info("Executing: composer update");
        echo shell_exec('composer update');
    }
}

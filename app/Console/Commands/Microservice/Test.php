<?php

namespace App\Console\Commands\Microservice;

use Illuminate\Console\Command;

class Test extends Command
{
    protected $signature = 'microservice:test {service?}';

    protected $description = '';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->startTest();
        app('log-system')->send();
    }

    protected function startTest()
    {
        $command = 'vendor/bin/phpunit --stop-on-failure --stop-on-error';
        $services = ['Hotel', 'Flight', 'Package'];

        if (!empty($this->argument('service'))) {
            $service = ucfirst($this->argument('service'));
            if (!in_array($service, $services)) {
                $this->error("Invalid srvice selected");
                return;
            }
            $command = "$command tests/$service";
        }
        $this->info("Executing: $command");
        echo shell_exec($command);
    }
}

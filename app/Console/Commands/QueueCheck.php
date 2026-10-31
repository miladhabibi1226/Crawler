<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearBuffers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checking if queue is running';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        app('log-system')->setPid('QueueCheck-'.uniqid());


        if(1){
            shell_exec(base_path() . "/artisan queue:work");
        }

    }
}
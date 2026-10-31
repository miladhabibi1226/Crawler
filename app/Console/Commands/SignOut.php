<?php

namespace App\Console\Commands;

use App\TravelServices\Handler;
use Illuminate\Console\Command;

class signOut extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'signout {nodeId : Node Id} {sessions : Sessions} {--p|pid= : PID} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        app('log-system')->setpid($this->option('pid'));
        switchNode($this->argument('nodeId'));
        Handler::signOut($this->argument('sessions'));
        app('log-system')->send();
    }
}

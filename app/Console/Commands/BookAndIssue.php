<?php

namespace App\Console\Commands;

use App\TravelServices\Handler;
use Illuminate\Console\Command;

class BookAndIssue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BookAndIssue {nodeId : Node Id} {referenceId : Booking Reference ID} {--p|pid= : PID} {--f|from-file : from file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Call handlers book method';

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
        startDBTransaction();
        switchNode($this->argument('nodeId'));
        request()->fromFile = $this->option('from-file') ?? false;
        if (request()->fromFile) {
            lugWarning('Reading the search result from file');
        }
        Handler::bookAndIssue($this->argument('referenceId'));
        commitDBTransaction();
        app('log-system')->send();
    }
}

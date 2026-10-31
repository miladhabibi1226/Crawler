<?php

namespace App\Console\Commands\Search;

use App\TravelServices\Handler;
use Illuminate\Console\Command;

class SearchVisa extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visa:search {nodeId : Node Id} {sellerId : Seller ID} {sessionId : Search Session ID (index of SearchQuery entity)} {--p|pid= : PID} {--l|lang= : lang} {--f|from-file : from file}';

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
        app('translator')->setLocale($this->option('lang'));
        switchNode($this->argument('nodeId'));
        request()->fromFile = $this->option('from-file')?? false;
        if(request()->fromFile){
            lugWarning('Reading the search result from file');
        }
        Handler::Search($this->argument('sellerId'), $this->argument('sessionId'));
        app('log-system')->send();
    }
}

<?php

namespace App\Console\Commands\schedule;

use App\Models\SupplierSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\Node;

class SuppliersSessionsKiller extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supplier-session-killer';

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
        app('log-system')->setPid('sessionKiller-'.uniqid());

        $nodeIds = Node::all()->pluck('id');
        foreach ($nodeIds as $id) {
            switchNode($id);
            // lugInfo(" Node: $id");
            $this->info("killing expired supplier sessions. Node " . $id);
            $sessionsByLicenceId = SupplierSession::where('expire_on', '<', Carbon::now())->select('seller_id', 'session')->get()->groupby('seller_id')->toArray();
            if (empty($sessionsByLicenceId)) {
                // lugInfo("No Sessions Found");
                $this->info("No Sessions Found");
                continue;
            }
            foreach ($sessionsByLicenceId as $sellerId => $sessionsList) {
                    $sessionsList = array_column($sessionsList, 'session');
                    shell_exec(base_path() . "/artisan signout $id '" . json_encode($sessionsList) . "' -p " . app('log-system')->getpid() . " > /dev/null 2>/dev/null &");
            }
        }
        $this->info('Done.');
        app('log-system')->send();
    }
}

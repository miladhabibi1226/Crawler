<?php

namespace App\Console\Commands\schedule;

use App\Models\SupplierSession;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ClearSuppliersSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear-supplier-session';

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
        app('log-system')->setPid('clearSessions-' . uniqid());

        foreach (allNodeIds() as $id) {
            switchNode($id);
            $sessions = SupplierSession::where('created_at', '<', Carbon::yesterday())->forceDelete();
            if (!empty($sessions)) {
                $this->info("Deleted $sessions old supplier sessions. Node $id");
                lugInfo("Deleted $sessions old supplier sessions. Node $id");
            }
        }
        $this->info('Done.');
        app('log-system')->send();
    }
}

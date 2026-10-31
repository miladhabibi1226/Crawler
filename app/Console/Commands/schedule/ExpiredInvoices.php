<?php

namespace App\Console\Commands\schedule;

use App\TravelServices\Handler;
use Carbon\Carbon;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\Booking;
use MatinUtils\NodeTracker\Models\Invoice;
use MatinUtils\NodeTracker\Models\Node;

class ExpiredInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expired-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks if there are any expired invoices.';

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
        app('log-system')->setPid('ExpiredInvoices-' . uniqid());
        $nodeIds = allNodeIds();
        foreach ($nodeIds as $id) {
            switchNode($id);

            $numberOfUpdatedInvoices = Invoice::where('type', 'manual')
                ->where('payment_status', types('paymentStatus', 'not_paid'))->where('due_date', '<', Carbon::now())
                ->update(['payment_status' => types('paymentStatus', 'not_authorized')]);

            if ($numberOfUpdatedInvoices > 0) {
                lugInfo("$numberOfUpdatedInvoices invoices expired for node $id");
            }
        }
        app('log-system')->send();
    }
}

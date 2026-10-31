<?php

namespace App\Console\Commands\schedule;

use App\TravelServices\Handler;
use Carbon\Carbon;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\Booking;
use MatinUtils\NodeTracker\Models\Node;

class AsyncTicketing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'async-ticketing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get ticket numbers for bookings';

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
        startDBTransaction();
        app('log-system')->setPid('ticketing-' . uniqid());

        $nodeIds = Node::all()->pluck('id');
        foreach ($nodeIds as $id) {
            switchNode($id);
            $bookings = Booking::where('async_action', true)->get();
            if (count($bookings) > 0) lugInfo(count($bookings) . ' Bookings found for node ' . $id);
            foreach ($bookings as $booking) {
                insertSupportRecord($booking->getSerial(), ['pid' => ['asyncTicketing' => app('log-system')->getPid()]]);
                Handler::asyncTicketing($booking->reference_id);
                $departureDate = $booking->service_info['query_params']['departure'];
                if ($departureDate < Carbon::yesterday()) {
                    lugWarning('AsyncTicketing cancelled bc the departure has passed' , [$departureDate]);
                    $booking->async_action = 0;
                }
                $booking->update();
            }
        }
        $this->info('Done.');
        commitDBTransaction();
        app('log-system')->send();
    }
}

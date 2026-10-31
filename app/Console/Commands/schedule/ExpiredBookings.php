<?php

namespace App\Console\Commands\schedule;

use App\TravelServices\Handler;
use Carbon\Carbon;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\Booking;
use MatinUtils\NodeTracker\Models\Node;

class ExpiredBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expired-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks if there are any expired bookings and fails them.';

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
        app('log-system')->setPid('ExpiredBookings-' . uniqid());
        $nodeIds = Node::all()->pluck('id');
        foreach ($nodeIds as $id) {
            switchNode($id);
            $bookings = Booking::where('status', types('bookingStatus', 'on_hold'))
                ->where('expire_on', '<', Carbon::now())->get();
            if (count($bookings) > 0) lugInfo(count($bookings) . ' Bookings found for node ' . $id);
            foreach ($bookings as $booking) {
                insertSupportRecord($booking->getSerial(), ['pid' => ['Expired' => app('log-system')->getPid()]]);
                Handler::expire($booking->reference_id);
                lugInfo('Booking Expires', ['booking' => $booking]);
            }
        }
        app('log-system')->send();
    }
}

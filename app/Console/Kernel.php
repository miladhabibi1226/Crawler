<?php

namespace App\Console;

use App\Console\Commands\CrawlerRun;
use App\Console\Commands\PM\Worker;
use App\Jobs\SepehranSchedule;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use MatinUtils\EasySocket\Commands\Serve;
use MatinUtils\ProcessManager\Commands\Run;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\BeforeBook::class,
        Commands\BookAndIssue::class,
        Commands\Testing::class,
        Commands\SignOut::class,
        Commands\PartoHotels::class,
        Commands\PartoHotelsSync::class,
        Commands\HotelImages::class,
        Commands\ActiveBookings::class,
        Commands\IssuedBooks::class,
        Commands\BookByType::class,

        //> create the folder/file structure for a new supplier
        Commands\NewSupplier\Common::class,
        Commands\NewSupplier\TravelService::class,

        ///> search travelServices
        Commands\Search\SearchHotel::class,
        Commands\Search\SearchFlight::class,
        Commands\Search\SearchPackage::class,
        Commands\Search\SearchTour::class,
        Commands\Search\SearchEvent::class,
        Commands\Search\SearchVisa::class,

        ///> create search
        Commands\Search\Create\Flight::class,
        Commands\Search\Create\GetSellers::class,


        ///> scheduled commands
        Commands\schedule\SuppliersSessionsKiller::class,
        Commands\schedule\ClearDatabaseBuffers::class,
        Commands\schedule\AsyncTicketing::class,
        Commands\schedule\ExpiredBookings::class,
        Commands\schedule\ExpiredInvoices::class,
        Commands\schedule\AirportFrequency::class,
        Commands\schedule\BookingTransactions::class,
        Commands\schedule\ClearSuppliersSessions::class,
        Commands\schedule\MissedLugs::class,


        ///> microservice commands
        Commands\Microservice\Update::class,
        Commands\Microservice\Upgrade::class,
        Commands\Microservice\Test::class,

        ///> ProcessManager commands
        Run::class,
        Worker::class,
        CrawlerRun::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->job(new SepehranSchedule())->daily();
        // $schedule->command('clear-supplier-session')->daily();
        // $schedule->command('supplier-session-killer')->everyMinute();
        $schedule->command('missedLugs')->dailyAt('01:45');
        // $schedule->command('expired-bookings')->everyMinute();
        // $schedule->command('expired-invoices')->everyFiveMinutes();
        // $schedule->command('clear-buffers')->everyTenMinutes();
        // $schedule->command('async-ticketing')->everyTwoMinutes();
        // $schedule->command('airport-frequency')->dailyAt(env('SCHEDULE_AIRPORT_FREQUENCY_START_TIME','23:59'));
        // if(!empty(env('SCHEDULE_TRANSACTIONS_START_TIME'))){
        //     $schedule->command('bookingTranssactions')->dailyAt('SCHEDULE_TRANSACTIONS_START_TIME');
        // }        
    }
}

<?php

namespace App\Console\Commands;

use App\Models\AirLineSupplier;
use App\Models\AirPlaneSupplier;
use App\Models\AirPortSupplier;
use App\Models\HotelProfile;
use App\Models\HotelSupplier;
use Illuminate\Console\Command;

class Redis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cach-redis';

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
        app('log-system')->setPid('REDIS-'.uniqid());

        $this->info('Airports:');
        $airports = AirPortSupplier::query()->with('airport.city')->get();        
        foreach ($airports as $airport) {
            app('redis')->command('hsetnx',["airports_cached",$airport->data_reference."_$airport->supplier_key",$airport->airport->toJson()]);
        }

        $this->info('Airplanes:');
        $airplanes = AirPlaneSupplier::query()->with('airplane')->get();
        foreach ($airplanes as $airplane) {
            app('redis')->command('hsetnx',["airplanes_cached",$airplane->data_reference."_$airplane->supplier_key",$airplane->airplane->toJson()]);
        }

        $this->info('Airlines:');
        $airlines = AirLineSupplier::query()->with('airline')->get();
        foreach ($airlines as $airline) {
            app('redis')->command('hsetnx',["airlines_cached",$airline->data_reference."_$airline->supplier_key",$airline->airline->toJson()]);
        }

        $this->info('Hotels:');
        $hotels = HotelSupplier::all();
        foreach ($hotels as $hotel) {
            app('redis')->command('hsetnx',["hotels_cached",$hotel->data_reference."_$hotel->supplier_key",$hotel->hotel_id]);
        }

        $this->info('HotelProfiles:');
        $hotelProfiles = HotelProfile::select('code', 'thumbnail')->get();
        foreach ($hotelProfiles as $hotelProfile) {
            app('redis')->command('hsetnx', ["hotel_profiles_cached", "$hotelProfile->code", json_encode(['thumbnail' => $hotelProfile->thumbnail])]);
        }
    }
}

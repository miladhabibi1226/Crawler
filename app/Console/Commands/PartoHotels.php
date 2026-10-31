<?php

namespace App\Console\Commands;

use App\Models\CommonHotelProfile;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\HotelSupplier;

class PartoHotels extends Command
{
    protected $signature = 'PartoHotels';

    protected $description = 'Upload images of hotel profiles';

    public function handle()
    {
        $hotelSuppliers = HotelSupplier::whereDataReference(types('supplierDataReference', 'Parto'))
            ->whereHas('hotel')->with('hotel.commonProfile')->get();
        $c = 0;
        foreach ($hotelSuppliers as $hotelSupplier) {
            $hotelProfile = $hotelSupplier->hotel->commonProfile;
            $fromPartoDatabase = app('db')->connection('parto')->table('parto_hotels')->where('id', $hotelProfile->code)->first();
            if (!empty($hotelProfile) 
            // && empty($hotelProfile->districts)
            ) {
                $fromPartoDatabase = app('db')->connection('parto')->table('parto_hotels')->where('id', $hotelProfile->code)->first();
                if (!empty($fromPartoDatabase->areaInfo)) {
                    $districts = json_decode($fromPartoDatabase->areaInfo, true);
                    dd($districts);
                    if (!empty($districts)) {
                        $districts = implode(',', array_column($districts ?? [], 'PlaceName'));
                        $hotelProfile->districts = $districts;
                        $hotelProfile->update();
                        dump($districts);
                        $c++;
                    }
                }
            }
        }
                        $this->info("$c items updated");
        app('log-system')->send();
    }
}

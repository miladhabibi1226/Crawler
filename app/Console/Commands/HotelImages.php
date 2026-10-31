<?php

namespace App\Console\Commands;

use App\Models\CommonHotelProfile;
use App\TravelServices\Handler;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\HotelProfile;

class HotelImages extends Command
{
    protected $signature = 'HotelImages';

    protected $description = 'Upload images of hotel profiles';

    public function handle()
    {
        $hotelProfiles = CommonHotelProfile::get();
        $this->UpdateProfiles($hotelProfiles);
        foreach (allNodeIds() as $nodeId) {
            switchNode($nodeId);
            $hotelProfiles = HotelProfile::get();
            $this->UpdateProfiles($hotelProfiles);
        }
        app('log-system')->send();
    }

    protected function UpdateProfiles($hotelProfiles)
    {
        $c = 0;
        foreach ($hotelProfiles as $hotelProfile) {
            if (!(str_starts_with($hotelProfile->images[0] ?? '', '20') ||str_starts_with($hotelProfile->images[0] ?? '', 'files') || str_starts_with($hotelProfile->images[0] ?? '', '/files') || str_starts_with($hotelProfile->images[0] ?? '', 'http://file-center.api'))) {
                // if ($c % 10 == 0) 
                $urls = (array) $hotelProfile->images;
                if (!empty($urls)) {
                    $this->info($c . ' - ' . $hotelProfile->id);
                    dump($hotelProfile->images[0]);
                    $response = ChunkUploadUrls($urls);
                    foreach ($response as $key => $jsonFile) {
                        if (!$jsonFile) {
                            continue;
                        } else {
                            $downloadedUrls[$key] = $jsonFile['date'] . '/' . $jsonFile['token'];
                        }
                    }
                    $updatedUrls = array_replace($urls, ($downloadedUrls ?? []));
                    lugInfo('Upload response', ['response' => $response, 'downloadedUrls' => $downloadedUrls ?? [], 'updatedImageList' => $updatedUrls]);
                    $hotelProfile->images = $updatedUrls;
                    $hotelProfile->update();
                    $c++;
                }
            }
        }

        $this->info("$c items updated");
    }
}

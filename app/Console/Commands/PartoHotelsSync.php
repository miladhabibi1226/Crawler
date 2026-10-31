<?php

namespace App\Console\Commands;

use App\Jobs\UploadUrls\UploadHotelProfilePics;
use App\Models\CommonHotelProfile;
use App\Models\SupplierSession;
use App\TravelServices\Handler;
use Illuminate\Console\Command;
use MatinUtils\NodeTracker\Models\HotelSupplier;
use MatinUtils\NodeTracker\Models\Seller;

class PartoHotelsSync extends Command
{
    protected $signature = 'PartoHotelsSync';

    protected $description = 'Sync hotel profiles with seedings';

    public function handle()
    {
        app('log-system')->setPid('PartoHotelsSync-' . uniqid());
        switchNode(1);
        $this->seller = Seller::find(63);
        $this->isDomestic = false;
        $chuncked = array_chunk($this->hotels() ?? [], 10);
        $totalCunks = count($chuncked);
        foreach ($chuncked as $chunkIndex => $hotels) {
            dump("chuncke $chunkIndex of $totalCunks");
            $existingHotels = CommonHotelProfile::whereIn('hotel_id', array_column($hotels, 'hotelId'))->pluck('hotel_id')->toArray();
            $this->hotels = $hotels;
            // foreach ($hotels as $key => $value) {
            //     if (in_array($value['hotelId'], $existingHotels)) {
            //         unset($this->hotels[$key]);
            //     }
            // }
            // if (empty($this->hotels)) continue;
            $fromPartoDatabase = app('db')->connection('parto')->table('parto_hotels')->whereIn('id', array_column($hotels, 'supplierKey'))->get();
            $imageLinks = $this->getImages($fromPartoDatabase);
            foreach ($hotels as $hotel) {
                $partoCode = $hotel['supplierKey'];
                $partoProfile = $fromPartoDatabase->where('id', $partoCode)->first();
                if (empty($partoProfile) || empty($imageLinks[$partoCode])) continue;
                $data[] = [
                    'hotel_id' => $hotel['hotelId'],
                    'code' => $partoProfile->id,
                    'title' => $partoProfile->name,
                    'phone' => $partoProfile->phone ?? null,
                    'thumbnail' => uploadUrl($imageLinks[0] ?? null, true),
                    'description' => $partoProfile->description ?? null,
                    'images' => !empty($imageLinks[$partoCode]) ? json_encode($imageLinks[$partoCode]) : '[]',
                    'address' => json_encode([
                        'lat' => $partoProfile->latitude ?? null,
                        'long' => $partoProfile->longitude ?? null,
                        'address' => $partoProfile->address ?? null,
                        'postal_code' => $partoProfile->postalCode,
                    ]),
                    'rating' => $partoProfile->rating ?? 0,
                    'extra_data' => json_encode([
                        'CheckIn' => $partoProfile->beginTime,
                        'CheckOut' => $partoProfile->checkOutTime,
                        'minAge' => $partoProfile->minAge,
                        'instructions' => $partoProfile->instructions,
                        'specialInstructions' => $partoProfile->specialInstructions,
                        'mandatoryFee' => $partoProfile->mandatoryFee
                    ]),
                ];
            }

            CommonHotelProfile::insertOrIgnore($data ?? []); ///> hotel might already exist if profile is opened
            $insertedProfiles = CommonHotelProfile::where('hotel_id', array_column($hotels, 'hotelId'))->pluck('id')->toArray();
            foreach ($insertedProfiles as $profileId) {
                dispatch(new UploadHotelProfilePics($profileId));
            }
        }
    }

    protected function getImages($fromPartoDatabase)
    {
        $latestSession = $this->findOrCreateSupplierSession();
        $endpoint = $this->isDomestic ? '/Rest/Hotel/DomesticHotelImage/' : '/Rest/Hotel/HotelImage';
        foreach (array_chunk($this->hotels, 10) as $hotels) {
            $multiCurl = curl_multi_init();
            $curls = [];
            foreach ($hotels as $hotel) {
                $partoCode = $this->isDomestic ? ((int) $hotel['supplierKey']) - 5000000 : $hotel['supplierKey'];
                $partoProfile = $fromPartoDatabase->where('id', $partoCode)->first();
                    dump( $this->seller->api->configs['url']  . $endpoint);
                if (empty($partoProfile)) continue;
                $options = [
                    CURLOPT_URL => $this->seller->api->configs['url'] . $endpoint,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 1,
                    CURLOPT_VERBOSE=> true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode([
                        "SessionId" => $latestSession,
                        "HotelId" => $partoCode
                    ]),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                ];
                $curls[$partoCode] = curl_init();
                curl_setopt_array($curls[$partoCode], $options);
                curl_multi_add_handle($multiCurl, $curls[$partoCode]);
            }
            $running = null;
            do {
                curl_multi_exec($multiCurl, $running);
            } while ($running);
            curl_multi_close($multiCurl);
            foreach ($curls as $partoCode =>  $curl) {
                $response = json_decode(curl_multi_getcontent($curls[$partoCode]), true);
                $responses[$partoCode] = array_column($response['Links'] ?? [], 'Link');
            }
        }
        return $responses ?? [];
    }

    public function findOrCreateSupplierSession()
    {
        $activeSession = SupplierSession::where('seller_id', $this->seller->id)->first();
        if (!empty($activeSession)) {
            $supplierSession = $activeSession;
            $latestSession = $supplierSession->session;
        } else {
            $signIn = Handler::signIn($this->seller);
            $supplierSession = $signIn['supplierSession'];
            $latestSession = $supplierSession->session;
        }
        return $latestSession;
    }

    protected function hotels()
    {
        return [
            [
                "supplierKey" => 755436,
                "hotelId" => 17202
            ],
            [
                "supplierKey" => 267750,
                "hotelId" => 1116
            ],
            [
                "supplierKey" => 1025567,
                "hotelId" => 17203
            ],
            [
                "supplierKey" => 11420390,
                "hotelId" => 17204
            ]
        ];
    }
}

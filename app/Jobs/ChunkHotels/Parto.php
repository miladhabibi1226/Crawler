<?php

namespace App\Jobs\ChunkHotels;

use App\Jobs\Job;
use App\Jobs\UploadUrls\UploadHotelProfilePics;
use App\Models\SupplierSession;
use App\TravelServices\Handler;
use App\Models\CommonHotelProfile;
use MatinUtils\NodeTracker\Models\Seller;

class Parto extends Job
{
    protected $pid, $nodeId, $hotels, $sellerDataReference, $isDomestic, $seller, $sellerId;
    public function __construct(string $pid, $nodeId, array $hotels, string $sellerDataReference, $sellerId, $isDomestic)
    {
        // lugWarning(__METHOD__, [$hotels, $sellerDataReference, $isDomestic, $sellerId]);
        $this->pid = $pid;
        $this->nodeId = $nodeId;
        $this->hotels = $hotels;
        $this->sellerDataReference = $sellerDataReference;
        $this->isDomestic = $isDomestic;
        $this->sellerId = $sellerId;
    }

    public function operate()
    {
        app('log-system')->setPid($this->pid);
        switchNode($this->nodeId);
        $this->seller = Seller::find($this->sellerId);
        $fromPartoDatabase = app('db')->connection('parto')->table('parto_hotels')->whereIn('id', array_column($this->hotels, 'supplierKey'))->get();
        $imageLinks = $this->getImages($fromPartoDatabase);
        foreach ($this->hotels as $hotel) {
            $partoCode = $hotel['supplierKey'];
            $partoProfile = $fromPartoDatabase->where('id', $partoCode)->first();
            if (empty($partoProfile) || empty($imageLinks[$partoCode])) continue;
            $data[] = [
                'hotel_id' => $hotel['hotelId'],
                'data_reference'=>$this->sellerDataReference,
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
        $insertedProfiles = CommonHotelProfile::where('hotel_id', array_column($this->hotels, 'hotelId'))->pluck('id')->toArray();
        foreach ($insertedProfiles as $profileId) {
            dispatch(new UploadHotelProfilePics($profileId));
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
                if (empty($partoProfile)) continue;
                $options = [
                    CURLOPT_URL => $this->seller->api->configs['url'] . $endpoint,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 1,
                    CURLOPT_INTERFACE =>  env('CURL_INTERFACE'),
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
}

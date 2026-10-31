<?php

namespace App\Jobs\ChunkHotels;

use App\Jobs\Job;
use App\Jobs\UploadUrls\UploadHotelProfilePics;
use App\Models\SupplierSession;
use App\TravelServices\Handler;
use App\Models\CommonHotelProfile;
use MatinUtils\NodeTracker\Models\Seller;

class GRS extends Job
{
    protected $pid, $nodeId, $fromSearch, $sellerDataReference, $isDomestic, $seller, $sellerId, $dataRefrence;
    public function __construct(string $pid, $nodeId, array $hotels, string $sellerDataReference, $sellerId, $dataRefrence)
    {
        // lugWarning(__METHOD__, [$hotels, $sellerDataReference, $sellerId]);
        $this->pid = $pid;
        $this->nodeId = $nodeId;
        $this->fromSearch = $hotels;
        $this->sellerDataReference = $sellerDataReference;
        $this->sellerId = $sellerId;
        $this->dataRefrence = $dataRefrence;
    }

    public function operate()
    {
        app('log-system')->setPid($this->pid);
        switchNode($this->nodeId);
        $this->seller = Seller::find($this->sellerId);
        $properties = collect(array_column(array_column($this->getImages(), 'value'), 'property'));

        foreach ($this->fromSearch as $searchHotel) {
            $code = $searchHotel['supplierKey'];
            $grsProfile = $properties->where('id', $code)->first();
            $imageLinks = array_column($grsProfile['images'], 'url');
            if (empty($grsProfile)) continue;
            $rules = collect($grsProfile['rules']);
            if (empty($rules->where('category', 'children') ?? [])) continue;
            $chechInOut = $rules->where('name', 'ورود و خروج') ?? [];
            if (count($chechInOut) > 1) {
                lugWarning('more than one rule for checkin checkout', [$chechInOut]);
                $chechInOut = [];
            } else {
                $chechInOut = $chechInOut[0] ?? [];
            }
            $childAge = null;
            foreach ($rules as $rule) {
                if ($rule['name'] == 'ورود و خروج') {
                    continue;
                }
                if ($rule['category'] == 'children') {
                    if (empty($rule['conditions'])) {
                        lugWarning("empty children rule conditions", [$grsProfile, $rule]);
                        app('log')->info("emptyconditions " . app('log-system')->getPid());
                        continue 2;
                    }
                    $childAge = $rule['conditions']['max_infant_age'] . ',' . $rule['conditions']['max_child_age'];
                    $children = $rule['conditions'];
                }
                $extrarules[$rule['name']] = ['fa' => $rule['description'], 'en' => $rule['description_en'], 'ar' => $rule['description_ar']];
            }
            // This is used to ensure that room stay duration is not less than one day TS2576:
            $ratePlans = collect($grsProfile['rate_plans'])
                ->mapWithKeys(function ($ratePlan) {
                    return [
                        $ratePlan['id'] => [
                            'min_stay' => $ratePlan['min_stay'] ?? null,
                            'max_stay' => $ratePlan['max_stay'] ?? null,
                        ]
                    ];
                })
                ->toArray();
            $data[] = [
                'hotel_id' => $searchHotel['hotelId'],
                'data_reference' =>   $this->dataRefrence,
                'code' => $grsProfile['id'],
                'title' => json_encode(['fa' => $grsProfile['name'], 'en' => $grsProfile['name_en'], 'ar' => $grsProfile['name_ar']]),
                "facilities" => json_encode(array_column($grsProfile['facilities'] ?? [], 'description')),
                'rooms' => json_encode($grsProfile['room_types'] ?? []),
                'phone' => null,
                'child_age' => $childAge,
                'thumbnail' => uploadUrl($imageLinks[0] ?? null, true),
                'description' => json_encode(['fa' => $grsProfile['description'], 'en' => $grsProfile['description_en'], 'ar' => $grsProfile['description_ar']]),
                'images' => !empty($imageLinks) ? json_encode($imageLinks) : null,
                'address' => json_encode([
                    'lat' => $grsProfile['latitude'] ?? null,
                    'long' => $grsProfile['longitude'] ?? null,
                    'address' => json_encode(['fa' => $grsProfile['address'] ?? null, 'en' => $grsProfile['address_en'], 'ar' => $grsProfile['address_ar']]),
                ]),
                'rating' => $grsProfile['star'] ?? 0,
                'extra_data' => json_encode([  ////>  Extra data will be updated in GRS AvailableRooms
                    'CheckIn' => $chechInOut['conditions']['check_in'] ?? null,
                    'CheckOut' => $chechInOut['conditions']['check_out'] ?? null,
                    'children' => $children ?? [],
                    'rules' => $extrarules ?? [],
                    'rate_plans' => json_encode($ratePlans ?? []),
                ]),
            ];
        }
        CommonHotelProfile::insertOrIgnore($data ?? []);
        $insertedProfiles = CommonHotelProfile::where('hotel_id', array_column($this->fromSearch, 'hotelId'))->pluck('id')->toArray();
        foreach ($insertedProfiles as $profileId) {
            dispatch(new UploadHotelProfilePics($profileId));
        }
    }

    protected function getImages()
    {
        ///> there is a properties api, calling it without the url param or any filters will return all the info of multiple holtels (max 100 in each page)
        ///> calling it with filters will ewturn the same data for the specified hotel(s), but the problem ids that in both cases some info is missing
        ///> the only way we can get the full info is to add the propertyId as a url param (we want the rules), so we have to use multiCurl

        $multiCurl = curl_multi_init();
        foreach ($this->fromSearch as $key => $hotel) {
            $code = $hotel['supplierKey'];
            $options = [
                CURLOPT_URL => $this->seller->api->configs['url'] . "/v1/properties/$code",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_INTERFACE =>  env('CURL_INTERFACE'),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Client-Token:' . $this->seller->api->configs['token']
                ),
            ];

            $curls[$key] = curl_init();
            curl_setopt_array($curls[$key], $options);
            curl_multi_add_handle($multiCurl, $curls[$key]);
        }
        $running = null;
        do {
            curl_multi_exec($multiCurl, $running);
        } while ($running);
        curl_multi_close($multiCurl);
        foreach ($curls as $key =>  $curl) {
            $body[$key] = json_decode(curl_multi_getcontent($curls[$key]), true);
        }
        return $body;
    }
}

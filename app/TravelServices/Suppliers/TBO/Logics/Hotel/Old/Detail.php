<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\Old;

use App\Jobs\UploadUrls\UploadHotelProfilePics;
use App\Models\CommonHotelProfile;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\HotelSupplier;

trait Detail
{
    protected function getContent()
    {
        if ($this->fromFile) {
            $xml = new \SimpleXMLElement(file_get_contents(storage_path('Samples/TBO/DetailResponse.xml')));
            $this->rawResult['body'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
            lugInfo('Reading the result from file');
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }

        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['body'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth;
        $this->call = [
            'fields' => view('TBO.HotelDetails', $depends)->render(),
            'url' => $auth['url'],
            'apiMethod' => 'Hotel Details'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $hotelDetails = $this->rawResult['body'][0]['HotelDetailsResponse']['HotelDetails'] ?? $this->callException('empty HotelDetails');
        $this->repelSingleMode($hotelDetails);
        $hotel = HotelSupplier::where('Supplier_key', $hotelDetails[0]['@attributes']['HotelCode'])->first();
        $map = explode('|', $hotelDetails[0]['Map']);
        $attractions = is_array($hotelDetails[0]['Attractions']['Attraction'] ?? null) ? json_encode($hotelDetails[0]['Attractions']['Attraction']) : $hotelDetails[0]['Attractions']['Attraction'] ?? null;
        $this->items = [
            'hotel_id' => $hotel->hotel_id,
            'code' => $hotelDetails[0]['@attributes']['HotelCode'],
            'title' => $hotelDetails[0]['@attributes']['HotelName'],
            'phone' => $hotelDetails[0]['PhoneNumber'] ?? null,
            'thumbnail' => uploadUrl($this->object->service_info['hotel']['thumbnail'] ?? null, true),
            'attractions' => $attractions,
            'facilities' => json_encode($hotelDetails[0]['HotelFacilities']) ?? null,
            'description' => $hotelDetails[0]['Description'] ?? null,
            'images' => !empty($hotelDetails[0]['ImageUrls']['ImageUrl']) ?  json_encode($hotelDetails[0]['ImageUrls']['ImageUrl']) : null,
            'address' => json_encode([
                'lat' => $map[0] ?? null,
                'long' => $map[1] ?? null,
                'address' => $hotelDetails[0]['Address'] ?? null,
                'postal_code' => 0, ///> todo: #46
            ]),
            'rating' => $this->star[$hotelDetails[0]['@attributes']['HotelRating'] ?? 'All'] ?? $this->star['All'],
            'trip_advisor' => $this->object->service_info['hotel']['tripadvisor_rating'] ?? null,
            'trip_advisor_url' => $this->object->service_info['hotel']['tripadvisor_review_url'] ?? null,
            'districts' => json_encode($this->object->service_info['hotel']['districts']) ?? null,
            'rooms' => json_encode($hotelDetails[0]['RoomInfo']) ?? null,
            'created_at' => Carbon::now()
        ];

        lugInfo('TBO HotelDetail extractor items', $this->items);
    }

    protected function mapper()
    {
        $address = $this->items['address'];
        $firstOrCreate = CommonHotelProfile::firstOrCreate($this->items);
        if ($firstOrCreate->code != $this->items['code']) { ///> means profile is simultaneously collected by another supplier
            if (empty($firstOrCreate->districts)) {
                $firstOrCreate->districts = $this->items['districts'];
            }
            if (empty($firstOrCreate->trip_advisor)) {
                $firstOrCreate->districts = $this->items['trip_advisor'];
            }
            if (empty($firstOrCreate->trip_advisor_url)) {
                $firstOrCreate->districts = $this->items['trip_advisor_url'];
            }
            if (empty($firstOrCreate->attractions)) {
                $firstOrCreate->districts = $this->items['attractions'];
            }
            if (empty($firstOrCreate->facilities)) {
                $firstOrCreate->districts = $this->items['facilities'];
            }
            $firstOrCreate->update();
        } else {
            lugInfo("dispatching 'UploadHotelProfilePics' job");
            dispatch(new UploadHotelProfilePics($firstOrCreate));
        }
        $seller = $this->object->seller_info;
        $seller['afters']['detail'] = true;
        $this->object->seller_info = $seller;
        $service = $this->object->service_info;
        $service['hotel']['phone'] = $this->items['phone'];
        $service['hotel']['address'] = json_decode($address, true)['address'];
        $this->object->service_info = $service;
        $this->object->update();
    }
}

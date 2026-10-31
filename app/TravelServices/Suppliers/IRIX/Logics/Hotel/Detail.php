<?php

namespace App\TravelServices\Suppliers\IRIX\Logics\Hotel;

use App\Jobs\UploadUrls\UploadHotelProfilePics;
use App\Models\CommonHotelProfile;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\HotelSupplier;

trait Detail
{
    ///> we can't remove getContent to common file, because we don't neeed to decode response,response already is an array
    public function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/IRIX/HotelSearch.json'));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $depends = $this->object->seller_info + $this->seller->api->configs;
        $this->LugTheResponse = 0;
        $this->LugTheRequest = 0;
        // lugInfo("IRIX Hotel Detail Data ({$this->seller->alias})", [$depends]);
        $this->multi = true;
        $this->call = [];
        $urls = ["hotels/v1/search/results/{$depends['srkToken']}/hotels/{$depends['hotelCode']}/details?token={$depends['resultsToken']}", "mapping/v1/hotels/{$depends['hotelCode']}/images"];
        foreach ($urls as $url) {
            $this->call[] = [
                'fields' => "",
                'method' => "GET",
                'url' =>  "{$depends['url']}/api/$url",
                'timeout' => 20,
                'apiMethod' => 'HotelDetail'
            ];
        }
        return $this->action();
    }

    protected function extractor()
    {
        if (empty($this->rawResult)) {
            return $this->addError('detail response is empty');
        }
        ///> get hotel profile
        $hotelProfileRawResult = $this->rawResult[0];
        $hotelProfile = json_decode($hotelProfileRawResult['body'] ?? [], true);
        if (empty($hotelProfile['id'])) {
            return $this->addError('hotel detail empty result');
        }
        ///> get hotel images
        $hotelImagesRawResult = $this->rawResult[1];
        $allImages = !empty(json_decode($hotelImagesRawResult['body'] ?? [], true)) ? json_decode($hotelImagesRawResult['body'], true)['_embedded']['gallery'] : [];
        $hotelImages = array_column($allImages, 'url');
        ///> hotel supplier
        $hotelSupplier = HotelSupplier::whereDataReference($this->dataReference)->where('Supplier_key', $this->object->seller_info['hotelCode'])->whereNotNull('hotel_id')->first();
        if (empty($hotelSupplier)) {
            return $this->addError('hotelSupplier empty');
        }
        $thumbnail = uploadUrl($this->object->service_info['hotel']['thumbnail'] ?? null, true);
        $this->items = [
            'hotel_id' => $hotelSupplier->hotel_id,
            'data_reference' => $this->dataReference,
            'code' => $hotelProfile['id'],
            'title' => $hotelProfile['name'],
            'phone' => $hotelProfile['telephone'] ?? null,
            'thumbnail' => empty($thumbnail) ? $this->object->service_info['hotel']['thumbnail'] ?? '' : $thumbnail,
            'facilities' => null,
            'description' => $hotelProfile['shortDescription'] ??  null,
            'images' => !empty($hotelImages) ? $hotelImages : null,
            'address' => [
                'lat' => $hotelProfile['geolocation']['latitude'] ?? null,
                'long' => $hotelProfile['geolocation']['longitude'] ?? null,
                'address' => $hotelProfile['address'] ?? null,
                'postal_code' => 0,
            ],
            'rating' => $this->object->service_info['hotel']['star'] ?? 0,
            'created_at' => Carbon::now()
        ];

        lugInfo('IRIX HotelDetail extractor items', $this->items);
    }

    protected function mapper()
    {
        $CommonHotelProfile = CommonHotelProfile::firstOrCreate(['hotel_id' => $this->items['hotel_id'], 'code' => $this->items['code']], $this->items);
        $seller = $this->object->seller_info;
        $seller['afters']['detail'] = true;
        $this->object->seller_info = $seller;
        $this->object->update();
        if (!str_starts_with($CommonHotelProfile->images[0] ?? '', '/files')) {
            dispatch(new UploadHotelProfilePics($CommonHotelProfile->hotel_id));
        }
    }
}

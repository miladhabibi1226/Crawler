<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Hotel;

use App\Jobs\UploadUrls\UploadHotelProfilePics;
use App\Models\CommonHotelProfile;
use MatinUtils\NodeTracker\Models\HotelSupplier;

trait Detail
{
    ///> we have a database with parto's hotel data. we get everything from there except for the images. we call an api to get the images
    protected function canBeDone()
    {
        $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        if ($this->fromFile) {
            $xml = new \SimpleXMLElement(file_get_contents(storage_path('Samples/Parto/DetailResponse.xml')));
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $this->latestSession = 'fromFile';
            return true;
        }
        $this->requestGenerator();
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        ///> it's not important if we can't get response since we only call this to get pictures
    }

    public function requestGenerator()
    {
        $endpoint = $this->isDomesticHotel() ? '/Rest/Hotel/DomesticHotelImage/' : '/Rest/Hotel/HotelImage';
        $this->call = [
            'fields' => json_encode(['SessionId' => $this->latestSession, 'HotelId' => $this->object->seller_info['rawHotelCode']]),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . $endpoint,
            'apiMethod' => 'Hotel Details'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $imageLinks = $this->rawResult['decoded']['Links'] ?? [];
        $imageLinks = array_column($imageLinks, 'Link');

        $fromPartoDatabase = app('db')->connection('parto')->table('parto_hotels')->where('id', $this->object->seller_info['hotelCode'])->get();
        if (count($fromPartoDatabase) > 1) {
            lugError('Hotel Duplicate', [$fromPartoDatabase]);
            return $this->addError('Hotel Duplicate');
        }
        $fromPartoDatabase = $fromPartoDatabase->first();
        $hotelSupplier = HotelSupplier::whereDataReference(types('supplierDataReference', 'Parto'))->where('Supplier_key', $this->object->seller_info['hotelCode'])->whereNotNull('hotel_id')->first();
        if (empty($hotelSupplier)) {
            lugError('hotelSupplier empty', [$fromPartoDatabase]);
            return $this->addError('hotelSupplier empty');
        }
        $this->items = [
            'hotel_id' => $hotelSupplier->hotel_id,
            'code' => $fromPartoDatabase->id,
            'title' => $fromPartoDatabase->name,
            'phone' => $fromPartoDatabase->phone ?? null,
            'thumbnail' => uploadUrl($imageLinks[0] ?? null, true),
            'description' => $fromPartoDatabase->description ?? null,
            'images' => $imageLinks ?? [],
            'address' => [
                'lat' => $fromPartoDatabase->latitude ?? null,
                'long' => $fromPartoDatabase->longitude ?? null,
                'address' => $fromPartoDatabase->address ?? null,
                'postal_code' => $fromPartoDatabase->postalCode,
            ],
            'rating' => $fromPartoDatabase->rating ?? 0,
            'extra_data' => [
                'CheckIn' => $fromPartoDatabase->beginTime,
                'CheckOut' => $fromPartoDatabase->checkOutTime,
                'minAge' => $fromPartoDatabase->minAge,
                'instructions' => $fromPartoDatabase->instructions,
                'specialInstructions' => $fromPartoDatabase->specialInstructions,
                'mandatoryFee' => $fromPartoDatabase->mandatoryFee
            ],
        ];
        //// districts areaInfo amenities

        lugInfo('Parto HotelDetail extractor items', $this->items);
    }

    protected function mapper()
    {
        $address = $this->items['address'];
        ///> we have a job that might have inserted this profile before
        $CommonHotelProfile = CommonHotelProfile::firstOrCreate(['hotel_id' => $this->items['hotel_id'], 'code' => $this->items['code']], $this->items);
        $seller = $this->object->seller_info;
        $seller['afters']['detail'] = true;
        $this->object->seller_info = $seller;
        $service = $this->object->service_info;
        $service['hotel']['phone'] = $this->items['phone'];
        $service['hotel']['address'] = $address['address'];
        $this->object->service_info = $service;
        $this->object->update();
        if (!str_starts_with($CommonHotelProfile->images[0] ?? '', '/files')) {
            dispatch(new UploadHotelProfilePics($CommonHotelProfile->id));
        }
    }
}

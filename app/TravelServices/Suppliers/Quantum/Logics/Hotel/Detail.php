<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Hotel;

use App\Models\CommonHotelProfile;

trait Detail
{
    protected function canBeDone()
    {
        if (!env('API_INTERNAL', true)) {
            ///> means the data is being provided from an apiOut outside of the network (with different database)
            ///> in this case if the hotel profile doesn't exist in this database we'll need to get it from the og node
            $hotelProfile = CommonHotelProfile::whereHotelId($this->object->hotel_id)->first();
            if (!empty($hotelProfile) && !$this->object->seller_info['ogSupplierNeedsDetail']) {
                ///> means no need to continue with this action
                ///> we don't add error tho, bc that will fail this action and that might cause a problem in marketplace
                ///> so we'll check this response in the common 
                return false;
            }
        }
        ///> if env('API_INTERNAL', true) and we're here it means the og supplier realy needs details so we need to continue
        return true;
    }

    protected function getContent()
    {
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Quantum/HotelDetail.json'));
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $this->latestSession = 'fromFile';
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    protected function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = [
            'requestType' => env('API_INTERNAL', true) ? 'internal' : 'internalWithData',
            'referenceId' => $this->object->seller_info['bufferReferenceId'],
            'type' => "hotel",
            'action' => "detail"
        ];
        // lugInfo('Request Data', [$depends]);
        $this->call = [
            'api' => 'v0/afterSearch',
            'host' => $auth['url'],
            'fields' => $depends,
            'apiMethod' => 'Hotel Details'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $rawResult = $this->rawResult['decoded']['data'];
        $this->items = [
            'phone' => $rawResult['phone'],
            'address' => $rawResult['address']
        ];
        if (!env('API_INTERNAL', true)) {
            ///> in this case the data of hotel Profile has been sent in the reply for us to insert
            $firstOrCreate = CommonHotelProfile::firstOrCreate($rawResult['hotelProfile']);
        }

        lugInfo('Quantum HotelDetail extractor items', $this->items);
    }

    protected function mapper()
    {
        $service = $this->object->service_info;
        $service['hotel']['phone'] = $this->items['phone'];
        $service['hotel']['address'] = $this->items['address'];
        $seller = $this->object->seller_info;
        $seller['afters']['detail'] = true;
        $this->object->seller_info = $seller;
        $this->object->service_info = $service;
        $this->object->update();
    }
}

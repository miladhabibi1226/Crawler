<?php

namespace App\TravelServices\Suppliers\Quantum\Logics;

trait PassengersValidation
{

    protected function getContent()
    {
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
        $depends = $this->getFields();
        // lugInfo('Request Data', [$depends]);
        $this->call = [
            'api' => 'v0/book/passengersValidation',
            'host' => $auth['url'],
            'fields' => $depends,
            'apiMethod' => 'Passengers Validation'
        ];
        return $this->action();
    }

    protected function getFields() ///> package re-writes this
    {
        $depends = [
            'passengersCount' => $this->object->seller_info['passengersCount'] ?? [],
            'passengersInfo' => $this->objectIsBooking ? $this->object->passenger_info : request()->passengersInfo,
            'type' => $this->travelService
        ];
        if ($this->workingWithACachedProfile()) {
            $cachedId = explode('/', $this->object->seller_info['cachedId']);
            $depends['cachedReference'] = $cachedId[0];
        } else {
            $depends['referenceId'] = $this->object->seller_info['bufferReferenceId'];
        }
        return $depends;
    }

    protected function extractor()
    {
        if ($this->rawResult['decoded']['status'] == true) {
            $this->items = [
                'leader' => $this->rawResult['decoded']['leader'],
                'passengersInfo' => $this->rawResult['decoded']['passengersInfo'] ?? null
            ];
        }
        lugInfo('Quantum passengers validations extractor items', ['passengersInfo' => $this->object->passenger_info, 'Items' => $this->items]);
    }

    protected function mapper()
    {
        if ($this->objectIsBooking) {
            $this->object->leader = $this->items['leader'];
            if (!empty($this->items['passengersInfo'])) {
                $this->object->passenger_info = $this->items['passengersInfo'];
            }
            $this->object->update();
        } else {
            return ['status' => true, 'passengersInfo' => $this->items['passengersInfo'], 'leader' => $this->items['leader']];
        }
    }

    protected function done()
    {
        return $this->rawResult['decoded'];
    }

    protected function failed($error = null)
    {
        return $this->rawResult['decoded'];
    }
}

<?php

namespace App\TravelServices\Logics\Flight;

use App\TravelServices\Logics\PassengerValidationHelper;
use Carbon\Carbon;

trait PassengersValidation
{
    use PassengerValidationHelper;
    protected $firstAdultPassenger = true, $passengersInfo = [], $passengersForm = [], $optionalItems = [],  $passengerData = [];
    protected $types = ['adults' => 'adult', 'children' => 'child', 'infants' => 'infant'];

    protected function validate()
    {
        $this->setData();
        if ($this->validatePassengersCount()) {
            if ($this->validatePassengerTypeCount()) {
                foreach ($this->passengersInfo as $type => $passengersType) {
                    if (!in_array($type, array_keys($this->types))) {
                        continue;
                    }
                    $counter = 1;
                    foreach ($passengersType as $key => $passenger) {
                        $this->passengerData = [
                            'type' => $type,
                            'key' => $key,
                            'number' => $counter,
                            'typeSingular' => $this->types[$type]
                        ];
                        if ($this->passengerIsLeader()) {
                            $this->leadPassengerValidation($passenger, $this->passengersForm['lead'] ?? []);
                            $this->leaderInfo = $passenger;
                            $this->firstAdultPassenger = false;
                        }
                        $this->allPassengersValidation($passenger, $this->passengersForm['all'] ?? []);
                        $counter++;
                        $this->passengerData = [];
                    }
                }
                $this->validateDuplicate();
                $this->extraValidation();
            }
        }
        if (array_key_exists('captcha', $this->object->seller_info['afters'] ?? [])) {
            $this->validateCaptcha();
        }
        lugInfo("$this->supplierName $this->travelService passengers validation items", ['passportExpiryData (for the last psngr)' => $this->passportExpiryData, '$this->objectIsBuffer' => $this->objectIsBuffer, 'request()->all()' => request()->all(), 'passengersInfo' => $this->items, 'errors' => $this->errors, 'passengersForm' => $this->passengersForm, 'optionalItems' => $this->optionalItems]);
    }

    protected function leadPassengerValidation($passenger, $passengersForm)
    {
    }

    protected function mapper()
    {
        ///> to avoid returning values other than 'adults' 'children and 'infants' we set them directly into $this->items
        if (!empty($this->passengersInfo['adults'])) {
            $this->items['adults'] = $this->passengersInfo['adults'];
        }
        if (!empty($this->passengersInfo['children'])) {
            $this->items['children'] = $this->passengersInfo['children'];
        }
        if (!empty($this->passengersInfo['infants'])) {
            $this->items['infants'] = $this->passengersInfo['infants'];
        }

        $this->leader = $this->getLeader();

        if ($this->objectIsBooking) {
            $this->object->passenger_info = $this->items;
            $this->object->leader = $this->leader;
            $this->object->update();
        }
    }

    protected function done()
    {
        return ['status' => true, 'passengersInfo' => $this->items, 'leader' => $this->leader];
    }

    protected function failed($error = null)
    {
        return ['status' => false, 'errors' => $this->errors];
    }

    protected function validatePassengerTypeCount()
    {
        $count = count($this->passengersInfo['adults'] ?? []);
        if ($count == 0) {
            $this->addError(trans('passengersValidation.adultsCount'), $this->messageKey());
            return false;
        }
        $serviceInfo = $this->getServiceInfo();
        if ($count != $serviceInfo['query_params']['adults']) {
            $this->addError(sprintf(trans('passengersValidation.passengerTypeCount'), 'adults', $count, $serviceInfo['query_params']['adults']), "passengers[adults]");
            return false;
        }
        $count = count($this->passengersInfo['children'] ?? []);
        if ($count != $serviceInfo['query_params']['children']) {
            $this->addError(sprintf(trans('passengersValidation.passengerTypeCount'), 'children', $count, $serviceInfo['query_params']['children']), "passengers[children]");
            return false;
        }
        $count = count($this->passengersInfo['infants'] ?? []);
        if ($count != $serviceInfo['query_params']['infants']) {
            $this->addError(sprintf(trans('passengersValidation.passengerTypeCount'), 'infants', $count, $serviceInfo['query_params']['infants']), "passengers[infants]");
            return false;
        }
        return true;
    }

    protected function getLastDateOfTrip()
    {
        if (empty($this->departure)) {
            $serviceInfo = $this->getServiceInfo();
            $legs = $serviceInfo['legs'];
            $lastLeg = end($legs)['segments'];
            $lastsegment = end($lastLeg);
            $lastSegmentDeparture = $lastsegment['departure']['rawTime'] ?? ($lastsegment['departure']['raw_time'] ?? $serviceInfo['query_params']['departure']);
            $this->departure = Carbon::parse($lastSegmentDeparture ?? null);
        }
    }

    protected function addToPassengerData($value, $key, $extraKey = null)
    {
        if (empty($extraKey)) {
            $this->passengersInfo[$this->passengerData['type']][$this->passengerData['key']][$key] = $value;
        } else {
            $this->passengersInfo[$this->passengerData['type']][$this->passengerData['key']][$key][$extraKey] = $value;
        }
    }

    protected function assessDuplications()
    {
        foreach ($this->getUniqueItems() as $index => $items) {
            foreach ($this->passengersInfo as $type => $passengersType) {
                foreach ($passengersType as $key => $passenger) {
                    foreach ($items as $item) {
                        $data[$index]["$type$key"] = ($data[$index]["$type$key"] ?? '') . ($passenger[$item] ?? '');
                    }
                }
            }
            $data[$index] = array_filter($data[$index]);
        }
        return array_filter($data);
    }
    protected function extraValidation()
    {
        //some suppliers needs to rewrite this : [ yet => 1-MystiFly]
    }
}

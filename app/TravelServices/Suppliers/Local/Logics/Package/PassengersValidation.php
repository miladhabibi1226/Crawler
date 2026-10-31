<?php

namespace App\TravelServices\Suppliers\Local\Logics\Package;

use Carbon\Carbon;
use App\TravelServices\Suppliers\Local\Logics\Flight\PassengersValidation as FlightPassengersValidation;

trait PassengersValidation
{
    use FlightPassengersValidation;

    protected $firstAdultPassenger = true, $passengersCount, $roomData = [], $leaderInfo = [], $ageRange = [], $optionalItems = [], $availablePassengersCount = [];

    protected function validate()
    {
        $this->setData();
        if ($this->validatePassengersCount()) {
            foreach ($this->passengersCount as $roomType => $roomTypeGroup) {
                if (empty($this->requestedAvailability['rooms'][$roomType])) {
                    $this->addError($this->transMessage('invalidRoomType', [$roomType]), $this->messageKey());
                    continue;
                }
                $roomCounter = 1;
                foreach ($roomTypeGroup as $roomKey => $roompassengerCount) {
                    $this->roomData = [
                        'type' => $roomType,
                        'key' => $roomKey,
                        'number' => $roomCounter
                    ];
                    if ($this->validatePassengerTypeCount()) {
                        foreach ($this->passengersInfo[$roomType][$roomKey] as $type => $passengersType) {
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
                    }
                    $roomCounter++;
                    $this->roomData = [];
                }
            }
            $this->validateDuplicate();
        }
        lugInfo("Local Package passengers validation items", ['passportExpiryData (for the last psngr)' => $this->passportExpiryData, 'passengersCount' => $this->passengersCount, 'passengersInfo' => $this->passengersInfo, 'errors' => $this->errors, 'passengersForm' => $this->passengersForm, 'optionalItems' => $this->optionalItems]);
    }

    protected function setData()
    {
        if ($this->objectIsBooking) {
            $this->passengersInfo = $this->object->passenger_info;
            $this->passengersCount = $this->object->service_info['passengersCount'] ?? [];
            $this->requestedAvailability = $this->object->service_info['sellectedAvailability'];
        } else {
            $this->passengersInfo = request()->passengersInfo;
            $this->requestedAvailability = $this->object->service_info['availabilities'][request('availability')] ?? [];
            $this->requestedAvailability['sellectedRooms'] = request('rooms');
            foreach ($this->requestedAvailability['sellectedRooms'] as $roomType => $roomTypeGroup) {
                foreach ($roomTypeGroup as $room) {
                    $passengersCount[$roomType][] = ['adults' => (int) $room['adults'] ?? 0, 'children' => (int) $room['children'] ?? 0, 'childrenWOBed' => (int) ($room['childWOBed'] ?? 0), 'infants' => (int) ($room['infants'] ?? 0)];
                }
            }
            $this->passengersCount = $passengersCount ?? [];
        }
        $this->availablePassengersCount = $this->availablePackagePassengerCount($this->requestedAvailability['rooms']);
        if (!is_array($this->requestedAvailability['ageRange'])) {
            $this->requestedAvailability['ageRange'] = json_decode($this->requestedAvailability['ageRange'], true);
        }
        $this->ageRange = array_map(function ($rangeOfType) {
            return explode('-', $rangeOfType);
        }, $this->requestedAvailability['ageRange']);
        $this->passengersForm = $this->object->seller_info['passengersForm'] ?? [];
        $this->optionalItems = $this->getOptionalItems();
        $this->types = ['adults' => 'adult', 'children' => 'child', 'childrenWOBed' => 'childWOBed', 'infants' => 'infant'];
    }

    protected function validatePassengerTypeCount()
    {
        $roomAvailablePassengersCount = $this->availablePassengersCount[$this->roomData['type']] ?? [''];
        if (empty($roomAvailablePassengersCount['maxAdults'])) {
            $this->addError('Validation is unavailable for this item', $this->messageKey());
            return false;
        }
        $aCount = count($this->passengersInfo[$this->roomData['type']][$this->roomData['key']]['adults'] ?? []);
        if ($aCount == 0) {
            $this->addError($this->transMessage('adultsCount'), $this->messageKey());
            return false;
        }

        if ($aCount > $roomAvailablePassengersCount['maxAdults']) {
            lugError('passengerc Count mismatch', ['rooms' => $this->requestedAvailability['rooms'], 'roomType' => $this->roomData['type'], '$roomAvailablePassengersCount' => $roomAvailablePassengersCount, '$aCount' => $aCount]);
            $this->addError($this->transMessage('roomMaxPassengersType', ['adults', $roomAvailablePassengersCount['maxAdults'], $aCount]), $this->messageKey());
            return false;
        }

        $cCount = count($this->passengersInfo[$this->roomData['type']][$this->roomData['key']]['children'] ?? []);
        if ($cCount > $roomAvailablePassengersCount['maxChildren']) {
            lugError('passengerc Count mismatch', ['rooms' => $this->requestedAvailability['rooms'], 'roomType' => $this->roomData['type'], '$roomAvailablePassengersCount' => $roomAvailablePassengersCount, '$cCount' => $cCount]);
            $this->addError($this->transMessage('roomMaxPassengersType', ['children', $roomAvailablePassengersCount['maxChildren'], $cCount]), $this->messageKey());
            return false;
        }

        $cWOCount = count($this->passengersInfo[$this->roomData['type']][$this->roomData['key']]['childrenWOBed'] ?? []);
        if ($cWOCount > $roomAvailablePassengersCount['maxChildrenWOBed']) {
            lugError('passengerc Count mismatch', ['$roomAvailablePassengersCount' => $roomAvailablePassengersCount, '$cWOCount' => $cWOCount]);
            $this->addError($this->transMessage('roomMaxPassengersType', ['childrenWOBed', $roomAvailablePassengersCount['maxChildrenWOBed'], $cWOCount]), $this->messageKey());
            return false;
        }

        $iCount = count($this->passengersInfo[$this->roomData['type']][$this->roomData['key']]['infants'] ?? []);
        if ($iCount > $roomAvailablePassengersCount['maxInfant']) {
            lugError('passengerc Count mismatch', ['rooms' => $this->requestedAvailability['rooms'], 'roomType' => $this->roomData['type'], '$roomAvailablePassengersCount' => $roomAvailablePassengersCount, '$iCount' => $iCount]);
            $this->addError($this->transMessage('roomMaxPassengersType', ['infants', $roomAvailablePassengersCount['maxInfant'], $iCount]), $this->messageKey());
            return false;
        }
        $totalCount = $aCount + $cCount + $cWOCount;
        if ($totalCount > $roomAvailablePassengersCount['maxCapacity']) {
            lugError('passengerc Count mismatch', ['rooms' => $this->requestedAvailability['rooms'], 'roomType' => $this->roomData['type'], '$roomAvailablePassengersCount' => $roomAvailablePassengersCount, '$totalCount' => $totalCount]);
            $this->addError($this->transMessage('roomMaxPassengersType', ['passengers', $roomAvailablePassengersCount['maxCapacity'], $totalCount]), $this->messageKey());
            return false;
        }

        if ($totalCount < $roomAvailablePassengersCount['minCapacity']) {
            lugError('passengerc Count mismatch', ['rooms' => $this->requestedAvailability['rooms'], 'roomType' => $this->roomData['type'], '$roomAvailablePassengersCount' => $roomAvailablePassengersCount, '$totalCount' => $totalCount]);
            $this->addError($this->transMessage('roomMinPassengersType', ['adults', $roomAvailablePassengersCount['minCapacity'], $aCount]), $this->messageKey());
            return false;
        }
        return true;
    }

    protected function mapper()
    {
        ///> to avoid returning values other than 'adults' 'children and 'infants' we set them directly into $this->items
        foreach ($this->passengersInfo as $roomType => $roomTypeGroup) {
            foreach ($roomTypeGroup as $roomIndex => $roomPassengers) {
                if (!empty($roomPassengers['adults'])) {
                    $this->items[$roomType][$roomIndex]['adults'] = array_values($roomPassengers['adults']);
                }
                if (!empty($roomPassengers['children'])) {
                    $this->items[$roomType][$roomIndex]['children'] = array_values($roomPassengers['children']);
                }
                if (!empty($roomPassengers['childrenWOBed'])) {
                    $this->items[$roomType][$roomIndex]['childrenWOBed'] = array_values($roomPassengers['childrenWOBed']);
                }
                if (!empty($roomPassengers['infants'])) {
                    $this->items[$roomType][$roomIndex]['infants'] = array_values($roomPassengers['infants']);
                }
            }
        }

        $this->leader = $this->getLeader();
        if ($this->objectIsBooking) {
            $this->object->passenger_info = $this->items;
            $this->object->leader = $this->leader;
            $this->object->update();
        }
    }

    protected function getLastDateOfTrip()
    {
        if (empty($this->departure)) {
            $this->departure = Carbon::parse($this->requestedAvailability['to']);
        }
    }

    protected function validateBirthdateByDeparture($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                preg_match("/\d{4}+-+\d{2}+-+\d{2}/", $passenger[$field] ?? null, $bd);
                if (empty($bd[0])) {
                    $this->addError($this->transMessage('invalidDateFormat', [$field]), $this->messageKey($field));
                } else {
                    $this->getLastDateOfTrip();
                    $birthdate = Carbon::parse($passenger[$field] ?? null);
                    list($minAdult, $maxAdult) = $this->ageRange['adult'];
                    list($minChild, $maxChild) = $this->ageRange['child'];
                    list($minChildWOBed, $maxChildWOBed) = $this->ageRange['childWOBed'];
                    list($minInfant, $maxInfant) = $this->ageRange['infant'];
                    if ($this->passengerData['type'] == 'adults') {
                        if ($this->departure->diffInYears($birthdate) < $minAdult || $this->departure->diffInYears($birthdate) >= $maxAdult) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minAdult, $maxAdult]), $this->messageKey($field));
                        }
                    }
                    if ($this->passengerData['type'] == 'children') {
                        if ($this->departure->diffInYears($birthdate) < $minChild || $this->departure->diffInYears($birthdate) >= $maxChild) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minChild, $maxChild]), $this->messageKey($field));
                        }
                    }
                    if ($this->passengerData['type'] == 'childrenWOBed') {
                        if ($this->departure->diffInYears($birthdate) < $minChildWOBed || $this->departure->diffInYears($birthdate) >= $maxChildWOBed) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minChildWOBed, $maxChildWOBed]), $this->messageKey($field));
                        }
                    }
                    if ($this->passengerData['type'] == 'infants') {
                        if ($this->departure->diffInYears($birthdate) < $minInfant || $this->departure->diffInYears($birthdate) >= $maxInfant) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minInfant, $maxInfant]), $this->messageKey($field));
                        }
                    }
                }
            }
        }
    }

    protected function validateAgeByDeparture($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                $this->getLastDateOfTrip();
                list($minAdult, $maxAdult) = $this->ageRange['adult'];
                list($minChild, $maxChild) = $this->ageRange['child'];
                list($minChildWOBed, $maxChildWOBed) = $this->ageRange['childWOBed'];
                list($minInfant, $maxInfant) = $this->ageRange['infant'];
                if ($this->passengerData['type'] == 'adults') {
                    if ($passenger[$field] < $minAdult || $passenger[$field] >= $maxAdult) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minAdult, $maxAdult]), $this->messageKey($field));
                    }
                }
                if ($this->passengerData['type'] == 'children') {
                    if ($passenger[$field] < $minChild || $passenger[$field] >= $maxChild) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minChild, $maxChild]), $this->messageKey($field));
                    }
                }
                if ($this->passengerData['type'] == 'childrenWOBed') {
                    if ($passenger[$field] < $minChildWOBed || $passenger[$field] >= $maxChildWOBed) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minChildWOBed, $maxChildWOBed]), $this->messageKey($field));
                    }
                }
                if ($this->passengerData['type'] == 'infants') {
                    if ($passenger[$field] < $minInfant || $passenger[$field] >= $maxInfant) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minInfant, $maxInfant]), $this->messageKey($field));
                    }
                }
            }
        }
    }

    protected function addToPassengerData($value, $key, $extraKey = null)
    {
        if (empty($extraKey)) {
            $this->passengersInfo[$this->roomData['type']][$this->roomData['key']][$this->passengerData['type']][$this->passengerData['key']][$key] = $value;
        } else {
            $this->passengersInfo[$this->roomData['type']][$this->roomData['key']][$this->passengerData['type']][$this->passengerData['key']][$key][$extraKey] = $value;
        }
    }

    protected function assessDuplications()
    {
        foreach ($this->getUniqueItems() as $index => $items) {
            foreach ($this->passengersInfo as $roomType => $roomTypePassengers) {
                foreach ($roomTypePassengers as $roomKey => $roomPassengers) {
                    foreach ($roomPassengers as $type => $passengersType) {
                        foreach ($passengersType as $key => $passenger) {
                            foreach ($items as $item) {
                                $data[$index]["$roomType$roomKey$type$key"] = ($data[$index]["$roomType$roomKey$type$key"] ?? '') . ($passenger[$item] ?? '');
                            }
                            $data[$index] = array_filter($data[$index]);
                        }
                    }
                }
            }
        }
        return array_filter($data);
    }
}

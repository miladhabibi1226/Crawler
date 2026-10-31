<?php

namespace App\TravelServices\Logics\Hotel;

use App\TravelServices\Logics\PassengerValidationHelper;
use Carbon\Carbon;

trait PassengersValidation
{
    use PassengerValidationHelper;
    protected $firstAdultPassenger = true, $passengersInfo = [], $passengersForm = [], $roomData = [], $chekin, $allNationalIds = [], $passengerData = [], $optionalItems = [], $leader;

    protected function validate()
    {
        $this->setData();
        if ($this->validatePassengersCount()) {
            $roomCounter = 1;
            foreach ($this->passengersInfo as $roomKey => $room) {
                $this->roomData = [
                    'key' => $roomKey,
                    'number' => $roomCounter
                ];
                if ($this->validatePassengerTypeCount($room)) {
                    $this->validateChildAgeList($room);
                    foreach ($room as $passengerType => $passengerTypeguests) {
                        if (!in_array($passengerType, ['adult', 'child'])) {
                            continue;
                        }
                        $guestCounter = 1;
                        foreach ($passengerTypeguests as $key => $guest) {
                            $this->passengerData = [
                                'type' => $passengerType,
                                'key' => $key,
                                'number' => $guestCounter,
                                'typeSingular' => $passengerType
                            ];
                            if ($this->passengerIsLeader()) {
                                $this->leadPassengerValidation($guest, $this->passengersForm['lead'] ?? []);
                                $this->leaderInfo = $guest;
                                $this->firstAdultPassenger = false;
                            }
                            $this->allPassengersValidation($guest, $this->passengersForm['all']);
                            $guestCounter++;
                            $roomCounter++;
                            $this->passengerData = [];
                        }
                    }
                    ///> to avoid returning values other than 'adult' and 'child' for each room, and to make sure their indexes are correct we set them directly into $this->items
                    $newRoom = ['adult' => array_values($this->passengersInfo[$roomKey]['adult']),];
                    if (!empty($this->passengersInfo[$roomKey]['child'])) {
                        $newRoom['child'] = $this->passengersInfo[$roomKey]['child'];
                    }
                    $this->items[] = $newRoom;
                }

                $this->roomData = [];
            }
            $this->validateDuplicate();
        }
        lugInfo("$this->supplierName Hotel passengers validation items", ['passportExpiryData (for the last psngr)' => $this->passportExpiryData, 'passengersInfo' => $this->items, 'errors' => $this->errors, 'passengersForm' => $this->passengersForm, 'optionalItems' => $this->optionalItems]);
    }

    protected function validateBirthdate($passenger, $field, $maxChild, $maxInf = 0)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                preg_match("/\d{4}+-+\d{2}+-+\d{2}/", $passenger[$field] ?? null, $bd);
                if (empty($bd[0])) {
                    $this->addError($this->transMessage('invalidDateFormat', [$field]), $this->messageKey($field));
                } else {
                    $age = Carbon::parse($bd[0])->age;
                    if ($this->passengerData['type'] == 'adult') {
                        if ($age <  $maxChild || $age >= 120) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $maxChild, 120]), $this->messageKey($field));
                        }
                    }
                    if ($this->passengerData['type'] == 'child') {
                        if ($age >= $maxChild) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $maxInf, $maxChild]), $this->messageKey($field));
                        }
                    }
                }
            }
        }
    }

    protected function validateAge($passenger, $field,  $maxChild)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                if ($this->passengerData['type'] == 'adult') {
                    if ($passenger[$field] < $maxChild || $passenger[$field] >= 120) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'],  $maxChild, 120]), $this->messageKey($field));
                    }
                }
                if ($this->passengerData['type'] == 'child') {
                    if ($passenger[$field] >= $maxChild) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'],  0, $maxChild]), $this->messageKey($field));
                    }
                }
            }
        }
    }

    protected function validateChildAgeList($room)
    {
        $searchChildAges = $this->object->service_info['query_params']['rooms_detail'][$this->roomData['key']]['child-age'] ?? [];
        $inputChildAges = $this->inputChildAges($room);
        if (empty($inputChildAges)) {
            return;
        }
        sort($searchChildAges);
        sort($inputChildAges);
        if ($inputChildAges != $searchChildAges) {
            lugError("child age order", [$inputChildAges, $searchChildAges]);
            $this->addError($this->transMessage('roomChildAge', [$this->roomData['number']]), $this->messageKey());
        }
    }

    protected function inputChildAges($room)
    {
        $ageCaption = $this->passengersForm['all']['age'][0]['caption'] ?? null;
        if (!empty($ageCaption)) {
            return array_column($room['child'] ?? [], $ageCaption) ?? [];
        }
        $birthdayCaption = $this->passengersForm['all']['birthdate'][0]['caption'] ?? null;
        if (!empty($birthdayCaption)) {
            return array_map(function ($item) {
                return (string) Carbon::parse($item)->age;
            }, array_column($room['child'] ?? [], $birthdayCaption) ?? []);
        }
        return [];
    }

    protected function validatePassengerTypeCount($room)
    {
        $count = count($room['adult'] ?? []);
        if ($count == 0) {
            $this->addError($this->transMessage('adultsCount'), $this->messageKey());
            return false;
        }
        $ogAdultCount = $this->object->service_info['query_params']['rooms_detail'][$this->roomData['key']]['adults'] ?? 0;
        if ($count != $ogAdultCount) {
            $this->addError($this->transMessage('passengerTypeCount', ['adults', $this->roomData['number'], $count, $ogAdultCount]), $this->messageKey());
            return false;
        }
        $count = count($room['child'] ?? []);
        $ogCildrenCount = $this->object->service_info['query_params']['rooms_detail'][$this->roomData['key']]['children'] ?? 0;
        if ($count != $ogCildrenCount) {
            $this->addError($this->transMessage('passengerTypeCount', ['children', $this->roomData['number'], $count, $ogAdultCount]), $this->messageKey());
            return false;
        }
        return true;
    }

    protected function mapper()
    {
        $this->leader = $this->getLeader();

        if ($this->objectIsBooking) {
            $this->object->passenger_info = $this->items;
            $this->object->leader = $this->leader;
            $this->object->update();
        }
    }

    protected function done()
    {
        if ($this->objectIsBuffer) {
            return ['status' => true, 'passengersInfo' => $this->items, 'leader' => $this->leader];
        }
        return ['status' => true];
    }

    protected function failed($error = null)
    {
        return ['status' => false, 'errors' => $this->errors];
    }

    protected function getLeader()
    {
        return ($this->items[0]['adult'][0][$this->getPassengerFormCaption('first_name')] ?? '-') . ' ' . ($this->items[0]['adult'][0][$this->getPassengerFormCaption('last_name')] ?? '-');
    }

    protected function leadPassengerValidation($guest)
    {
    }

    protected function addToPassengerData($value, $key, $extraKey = null) ///> flight tour and event use this
    {
        if (empty($extraKey)) {
            $this->passengersInfo[$this->roomData['key']][$this->passengerData['type']][$this->passengerData['key']][$key] = $value;
        } else {
            $this->passengersInfo[$this->roomData['key']][$this->passengerData['type']][$this->passengerData['key']][$key][$extraKey] = $value;
        }
    }

    protected function getLastDateOfTrip()
    {
        if (empty($this->departure)) {
            $this->departure = Carbon::parse($this->queryParams['checkout']);
        }
    }

    protected function assessDuplications()
    {
        foreach ($this->getUniqueItems() as $index => $items) {
            foreach ($this->passengersInfo as $roomKey => $roomPassengers) {
                foreach ($roomPassengers as $type => $passengersType) {
                    foreach ($passengersType as $key => $passenger) {
                        foreach ($items as $item) {
                            $data[$index]["$roomKey$type$key"] = ($data[$index]["$roomKey$type$key"] ?? '') . ($passenger[$item] ?? '');
                        }
                        $data[$index] = array_filter($data[$index]);
                    }
                }
            }
        }
        return array_filter($data);
    }
}

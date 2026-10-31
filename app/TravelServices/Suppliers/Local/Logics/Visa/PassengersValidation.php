<?php

namespace App\TravelServices\Suppliers\Local\Logics\Visa;

use App\TravelServices\Logics\PassengerValidationHelper;
use App\TravelServices\Suppliers\Local\Logics\PassengersValidation as LogicsPassengersValidation;
use Carbon\Carbon;

trait PassengersValidation
{
    use PassengerValidationHelper, LogicsPassengersValidation;
    protected $leader = '', $allNationalIds = [], $passengersInfo = [], $passengersForm = [], $optionalItems = [];

    protected function validate()
    {
        $this->setData();
        if ($this->validatePassengersCount()) {
            $counter = 1;
            foreach ($this->passengersInfo as $key => $passenger) {
                $this->passengerData = [
                    'key' => $key,
                    'number' => $counter,
                ];
                ///> The passengers form is dynamic so we need to check if each type exists in the passengers form, and the do the required validation according to the type (eg. dates and countries need espacial validations)

                ///> First, we check the lead passengers info according to the items in the passengers form
                if ($counter == 1) {
                    $this->personValidation($passenger, $this->passengersForm['lead'] ?? []);
                }

                ///> Now we check the info that are common for all passengers
                $this->personValidation($passenger, $this->passengersForm['all']);

                $counter++;
                $this->passengerData = [];
            }
            $this->validateDuplicate();
        }
        lugInfo('Local Visa passengers validation items', ['passportExpiryData (for the last psngr)' => $this->passportExpiryData, 'leader' => $this->leader, 'requested passengersInfo' => $this->passengersInfo, 'passengersForm' => $this->passengersForm, 'passengersInfo' => $this->items, 'errors' => $this->errors, 'optionalItems' => $this->optionalItems]);
    }

    protected function validatePassengersCount()
    {
        $pax = $this->object->service_info['visa']['pax'];
        if (empty($this->passengersInfo) || count($this->passengersInfo) > $pax) {
            $this->addError($this->transMessage('passengerCountBetween', [1, $pax]), $this->messageKey());
            return false;
        }
        return true;
    }

    protected function personValidation($passenger, $passengersForm)
    {
        foreach ($passengersForm  as $itemType => $itemGroup) {
            if (in_array($itemType, ['photo', 'age', 'passport_number', 'text', 'text_area'])) {
                foreach ($itemGroup as $item) {
                    $this->validateRequired($passenger, $item['caption']);
                }
            }

            if ($itemType == 'phone') {
                foreach ($itemGroup as $item) {
                    $this->validatePhone($passenger, $item['caption']);
                }
            }

            if ($itemType == 'nationalId') {
                foreach ($itemGroup as $item) {
                    $this->validateCodeMelli($passenger, $item['caption']);
                }
            }

            if ($itemType == 'gender') {
                foreach ($itemGroup as $item) {
                    $this->validateGender($passenger, $item['caption']);
                }
            }

            if (in_array($itemType, ['nationality', 'passport_country', 'country'])) {
                foreach ($itemGroup as $item) {
                    $this->validateCountry($passenger, $item['caption'], $item['caption']);
                }
            }

            if (in_array($itemType, ['first_name', 'last_name', 'english_text'])) {
                foreach ($itemGroup as $item) {
                    $this->validateLatinNames($passenger, $item['caption']);
                }
            }

            if (in_array($itemType, ['birthdate', 'date'])) {
                foreach ($itemGroup as $item) {
                    $this->validateDate($passenger, $item['caption']);
                }
            }

            if ($itemType == 'passport_expiry') {
                foreach ($itemGroup as $item) {
                    $this->validatePassportExpiry($passenger, $item['caption']);
                }
            }

            if ($itemType == 'email') {
                foreach ($itemGroup as $item) {
                    $this->validateEmail($passenger, $item['caption']);
                }
            }
        }
    }

    protected function mapper()
    {
        $this->items = array_values($this->passengersInfo);

        $firstNameCaption = $this->getPassengerFormCaption('first_name');
        $lastNameCaption = $this->getPassengerFormCaption('last_name');
        $this->leader = ($this->items[0][$firstNameCaption] ?? '') . ' ' . ($this->items[0][$lastNameCaption] ?? '');
        if ($this->objectIsBooking) {
            $this->object->passenger_info = $this->items;
            if (empty($this->errors)) {
                foreach ($this->items as $passenger) {
                    $documents[] = [
                        'first_name' => $passenger[$firstNameCaption],
                        'last_name' => $passenger[$lastNameCaption],
                        'visa_status' => 'under_process',
                        'documents' => [],
                        'comments' => []
                    ];
                }
                $this->object->booking_info = array_merge($this->object->booking_info, ['passengers' => $documents ?? []]);
            }
            $this->object->leader = $this->leader;
            $this->object->update();
        }
    }

    protected function done()
    {
        if (!$this->objectIsBooking) {
            return ['status' => true, 'leader' => $this->leader, 'passengersInfo' => $this->items];
        }
        return ['status' => true];
    }

    protected function failed($error = null)
    {
        return ['status' => false, 'errors' => $this->errors];
    }

    protected function supplierNameTitle($gender)
    {
        return strtoupper($gender) == 'MALE' ? 'Mr' : 'Ms';;
    }

    protected function addToPassengerData($value, $key, $extraKey = null)
    {
        if (empty($extraKey)) {
            $this->passengersInfo[$this->passengerData['key']][$key] = $value;
        } else {
            $this->passengersInfo[$this->passengerData['key']][$key][$extraKey] = $value;
        }
    }

    protected function getLastDateOfTrip()
    {
        if (empty($this->departure)) {
            $this->departure = Carbon::now();
        }
    }

    protected function assessDuplications()
    {
        foreach ($this->getUniqueItems() as $index => $items) {
            foreach ($this->passengersInfo as $key => $passenger) {
                foreach ($items as $item) {
                    $data[$index][$key] = ($data[$index][$key] ?? '') . ($passenger[$item] ?? '');
                }
                $data[$index] = array_filter($data[$index]);
            }
        }
        return array_filter($data);
    }
}

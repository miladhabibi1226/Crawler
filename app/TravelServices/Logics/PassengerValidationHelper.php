<?php

namespace App\TravelServices\Logics;

use Carbon\Carbon;

trait PassengerValidationHelper
{
    protected $defaultMinPassportValidity = 6; ///> months
    protected $passportExpiryData = [];

    protected function setData()
    {
        $this->passengersInfo = $this->object->passenger_info;
        $this->passengersForm = $this->object->seller_info['passengersForm'] ?? [];
        $this->optionalItems = $this->getOptionalItems();
    }

    protected function messageKey($field = null, $extraField = null)
    {
        if ($field == 'captcha') {
            return "captcha";
        }
        switch ($this->travelService) {
            case 'flight':
            case 'event':
            case 'tour':
                $key =  "passengers";
                if (!empty($this->passengerData['type'])) {
                    $key .= "[" . $this->passengerData['type'] . "]";
                    if (!is_null($this->passengerData['key'])) {
                        $key .= "[" . $this->passengerData['key'] . "]";
                        if (!empty($field)) {
                            $key .= "[" . $field . "]";
                            if (!empty($extraField)) {
                                $key .= "[" . $extraField . "]";
                            }
                        }
                    }
                }
                break;
            case 'visa':
                $key =  "passengers";
                if (!is_null(($this->passengerData['key'] ?? null))) {
                    $key .= "[" . $this->passengerData['key'] . "]";
                    if (!empty($field)) {
                        $key .= "[" . $field . "]";
                        if (!empty($extraField)) {
                            $key .= "[" . $extraField . "]";
                        }
                    }
                }
                break;
            case 'hotel':
                $key =  "rooms";
                if (!is_null($this->roomData['key'] ?? null)) {
                    $key .= "[" . $this->roomData['key'] . "]";
                    if (!empty($this->passengerData['type'])) {
                        $key .= "[" . $this->passengerData['type'] . "]";
                        if (!is_null($this->passengerData['key'])) {
                            $key .= "[" . $this->passengerData['key'] . "]";
                            if (!empty($field)) {
                                $key .= "[" . $field . "]";
                                if (!empty($extraField)) {
                                    $key .= "[" . $extraField . "]";
                                }
                            }
                        }
                    }
                }
                break;
            case 'package':
                $key =  "passengers";
                if (!empty($this->roomData['type'])) {
                    $key .= "[" . $this->roomData['type'] . "]";
                    if (!is_null($this->roomData['key'])) {
                        $key .= "[" . $this->roomData['key'] . "]";
                        if (!empty($this->passengerData['type'])) {
                            $key .= "[" . $this->passengerData['type'] . "]";
                            if (!is_null($this->passengerData['key'])) {
                                $key .= "[" . $this->passengerData['key'] . "]";
                                if (!empty($field)) {
                                    $key .= "[" . $field . "]";
                                    if (!empty($extraField)) {
                                        $key .= "[" . $extraField . "]";
                                    }
                                }
                            }
                        }
                    }
                }
        }
        return $key;
    }

    protected function transMessage($messageTitle, $data = [])
    {
        return sprintf(trans("passengersValidation.$messageTitle"), ...$data);
    }

    protected function validatePassengersCount()
    {
        if (empty($this->passengersInfo)) {
            $this->addError($this->transMessage('emptyPassengersInfo'), $this->messageKey());
            return false;
        }
        return true;
    }

    protected function validateRequired($passenger, $field)
    {
        if (!in_array($field, $this->optionalItems)) {
            if (empty($passenger[$field])) {
                $this->addError($this->transMessage('required', [$field]), $this->messageKey($field));
                return false;
            }
        }
        return true;
    }

    protected function validateLatinNames($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                preg_match('/^[A-Za-z\s]+/', $passenger[$field], $output_array);
                if (strlen($output_array[0] ?? "") != strlen($passenger[$field])) {
                    $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                } else {
                    $this->addToPassengerData(strtoupper(str_replace(' ', '', $passenger[$field])), $field);
                }
            }
        }
    }

    protected function validatFarsiNames($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                preg_match('/^[ آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهیئ\s]+$/', $passenger[$field], $output_array);
                if (strlen($output_array[0] ?? "") != strlen($passenger[$field])) {
                    $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                }
            }
        }
    }

    protected function validateDate($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                preg_match("/\d{4}+-+\d{2}+-+\d{2}/", $passenger[$field] ?? null, $bd);
                if (empty($bd[0])) {
                    $this->addError($this->transMessage('invalidDateFormat', [$field]), $this->messageKey($field));
                }
            }
        }
    }

    protected function validateBirthdate($passenger, $field, $minChild, $maxChild)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                preg_match("/\d{4}+-+\d{2}+-+\d{2}/", $passenger[$field] ?? null, $bd);
                if (empty($bd[0])) {
                    $this->addError($this->transMessage('invalidDateFormat', [$field]), $this->messageKey($field));
                } else {
                    $age = Carbon::parse($bd[0])->age;
                    if ($this->passengerData['type'] == 'adults') {
                        if ($age <  $maxChild || $age >= 120) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $maxChild, 120]), $this->messageKey($field));
                        }
                    }
                    if ($this->passengerData['type'] == 'children') {
                        if ($age <  $minChild || $age >= $maxChild) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minChild, $maxChild]), $this->messageKey($field));
                        }
                    }
                    if ($this->passengerData['type'] == 'infants') {
                        $age = Carbon::parse($bd[0]);
                        if ($age->age >= $minChild || $age >= Carbon::now()) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], 0, $minChild]), $this->messageKey($field));
                        }
                    }
                }
            }
        }
    }

    protected function validateBirthdateByDeparture($passenger, $field, $minChild, $maxChild)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                preg_match("/\d{4}+-+\d{2}+-+\d{2}/", $passenger[$field] ?? null, $bd);
                if (empty($bd[0])) {
                    $this->addError($this->transMessage('invalidDateFormat', [$field]), $this->messageKey($field));
                } else {
                    $this->getLastDateOfTrip();
                    $birthdate = Carbon::parse($passenger[$field] ?? null);
                    if ($this->passengerData['type'] == 'adults') {
                        if ($this->departure->diffInYears($birthdate) < $maxChild || $this->departure->diffInYears($birthdate) >= 120) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $maxChild, 120]), $this->messageKey($field));
                        }
                    }
                    if ($this->passengerData['type'] == 'children') {
                        if ($this->departure->diffInYears($birthdate) < $minChild || $this->departure->diffInYears($birthdate) >= $maxChild) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minChild, $maxChild]), $this->messageKey($field));
                        }
                    }
                    if ($this->passengerData['type'] == 'infants') {
                        if ($this->departure->diffInYears($birthdate) >= $minChild || $birthdate->isFuture()) {
                            $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], 0, $minChild]), $this->messageKey($field));
                        }
                    }
                }
            }
        }
    }

    protected function validateAge($passenger, $field, $minChild, $maxChild)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                if ($this->passengerData['type'] == 'adults') {
                    if ($passenger[$field] < $maxChild || $passenger[$field] >= 120) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $maxChild, 120]), $this->messageKey($field));
                    }
                }
                if ($this->passengerData['type'] == 'children') {
                    if ($passenger[$field] < $minChild || $passenger[$field] >= $maxChild) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minChild, $maxChild]), $this->messageKey($field));
                    }
                }
                if ($this->passengerData['type'] == 'infants') {
                    if ($passenger[$field] >= $minChild || $passenger[$field] <= 0) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], 0, $minChild]), $this->messageKey($field));
                    }
                }
            }
        }
    }

    protected function validateAgeByDeparture($passenger, $field, $minChild, $maxChild)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                $this->getLastDateOfTrip();
                if ($this->passengerData['type'] == 'adults') {
                    if ($passenger[$field] < $maxChild || $passenger[$field] >= 120) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $maxChild, 120]), $this->messageKey($field));
                    }
                }
                if ($this->passengerData['type'] == 'children') {
                    if ($passenger[$field] < $minChild || $passenger[$field] >= $maxChild) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], $minChild, $maxChild]), $this->messageKey($field));
                    }
                }
                if ($this->passengerData['type'] == 'infants') {
                    if ($passenger[$field] >= $minChild || $passenger[$field] <= 0) {
                        $this->addError($this->transMessage('invalidAge', [$this->passengerData['type'], 0, $minChild]), $this->messageKey($field));
                    }
                }
            }
        }
    }

    protected function validatePassportExpiry($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                $this->getLastDateOfTrip();
                try {
                    $expiry = Carbon::parse($passenger[$field] ?? null);
                    ///> departure will be used in other places  i modified expiry instead
                    if ($expiry->subMonths($this->getMinPassportValidity($passenger)) < $this->departure) {
                        $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                    }
                } catch (\Throwable $th) {
                    lugError('passport_expiry parse: ' . $th->getMessage(), ['exceptionLine' => $th->getFile() . ' line: ' . $th->getLine(), 'getTraceAsString' =>  $th->getTraceAsString()]);
                    $this->addError($this->transMessage('invalidDateFormat', [$field]), $this->messageKey($field));
                }
            }
        }
    }

    protected function validateDateExpiry($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                $this->getLastDateOfTrip();
                try {
                    $expiry = Carbon::parse($passenger[$field] ?? null);
                    ///> departure will be used in other places  i modified expiry instead
                    if ($expiry < $this->departure) {
                        $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                    }
                } catch (\Throwable $th) {
                    lugError('passport_expiry parse: ' . $th->getMessage(), ['exceptionLine' => $th->getFile() . ' line: ' . $th->getLine(), 'getTraceAsString' =>  $th->getTraceAsString()]);
                    $this->addError($this->transMessage('invalidDateFormat', [$field]), $this->messageKey($field));
                }
            }
        }
    }

    protected function validatePassportNumber($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                preg_match('/^[A-Za-z0-9]+/', $passenger[$field], $output_array);
                if (strlen($output_array[0] ?? "") != strlen($passenger[$field])) {
                    $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                }
            }
        }
    }

    protected function validateCountry($passenger, $field, $countriesInfoKey = null)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                if (!is_numeric($passenger[$field])) {
                    $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                    return false;
                }
                $country = $this->getCountry($passenger[$field]);
                if (empty($country)) {
                    $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                    return false;
                }
                $this->addToPassengerData($country, 'CountriesInfo', $field);
                return $country;
            }
        }
    }

    protected function validateCity($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                $city = $this->getCityWithCountry($passenger[$field]);
                if (empty($city)) {
                    $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                }
                $this->addToPassengerData($city, 'cityInfo');
                return $city;
            }
        }
    }

    protected function validateEmail($passenger, $field) ///> rewritten n parto
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                if (!filter_var($passenger[$field], FILTER_VALIDATE_EMAIL)) {
                    $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                }
            }
        }
    }

    protected function validateGender($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                if (!in_array(strtoupper($passenger[$field]), ['MALE', 'FEMALE'])) {
                    $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                }
                $title = $this->supplierNameTitle($passenger[$field]);
                $this->addToPassengerData($title, 'title');
                return $title;
            }
        }
    }

    protected function validatePassportNationalId($passenger)
    {
        if ((empty($passenger['National Id']) && empty($passenger['National ID'])) && empty($passenger['Passport Number'])) { ///> will clean up later
            $this->addError($this->transMessage('passportNationalId'), $this->messageKey());
        }
    }

    protected function validateCodeMelli($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                if (!$this->check_national_code($passenger[$field])) {
                    $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                }
            }
        }
    }

    protected function validateCodeMelliNotRequired($passenger, $field)
    {
        if (!empty($passenger[$field])) {
            if (!$this->check_national_code($passenger[$field])) {
                $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
            }
        }
    }

    protected function validatePhone($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                if (empty($passenger[$field]['countryPhoneCode'])) {
                    $this->addError($this->transMessage('requiredPhoneCode', [$field]), $this->messageKey($field, 'countryPhoneCode'));
                }
                if (empty($passenger[$field]['phone'])) {
                    $this->addError($this->transMessage('required', [$field]), $this->messageKey($field, 'phone'));
                } else {
                    preg_match_all('/^0|[\D]/', $passenger[$field]['phone'], $output_array);
                    if (preg_match_all('/^0|[\D]/', $passenger[$field]['phone'], $output_array)) {
                        $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field, 'phone'));
                    }
                }
            }
        }
    }


    protected function validateCaptcha()
    {
        if (empty($this->object->seller_info['captchaValue'])) {
            $this->addError($this->transMessage('required', ['captcha']), $this->messageKey('captcha'));
        }
    }

    protected function check_national_code($code)
    {
        $code = (string) preg_replace('/[^0-9]/', '', $code);
        if (strlen($code) != 10) {
            return false;
        }

        $list_code = str_split($code);
        $last      = (int) $list_code[9];
        unset($list_code[9]);
        $i   = 10;
        $sum = 0;

        foreach ($list_code as $key => $tmp) {
            $sum += intval($tmp) * $i--;
        }

        $mod = (int) $sum % 11;

        if ($mod >= 2) {
            $mod = 11 - $mod;
        }

        return $mod == $last;
    }

    protected function passengerIsLeader()
    {
        ///> can't use the regular ones. becase several roomTypes and the 1st one may not be an adult
        return $this->passengerData['typeSingular'] == 'adult' && $this->firstAdultPassenger;
    }

    protected function getLeader()
    {
        return ($this->leaderInfo[$this->getPassengerFormCaption('first_name')] ?? '-') . ' ' . ($this->leaderInfo[$this->getPassengerFormCaption('last_name')] ?? '-');
    }

    protected function passengerIsLeaderVisa()
    {
        return $this->passengerData['number'] == 1;
    }

    protected function supplierNameTitle($gender)
    {
        if ($this->passengerData['typeSingular'] == 'adult') {
            $title = strtoupper($gender) == 'MALE' ? 'Mr' : 'Ms';
        } else {
            $title = strtoupper($gender) == 'MALE' ? 'Mstr' : 'Miss';
        }
        return $title;
    }

    protected function getOptionalItems()
    {
        foreach ($this->passengersForm as $type => $typeItems) {
            foreach ($typeItems as $items) {
                if (!is_array($items)) continue; ///> captcha is not an array
                foreach ($items as $item) {
                    if ($item['optional'] ?? false) {
                        $optionalItems[] = $item['caption'];
                    }
                }
            }
        }
        return $optionalItems ?? [];
    }

    protected function getMinPassportValidity($passenger)
    {
        if (empty($this->seller->api_id)) { ///> Could not rewrite bc there was a conflict and i didn't have the time to fix it
            return $this->getMinPassportValidityForLocals($passenger);
        }
        return $this->defaultMinPassportValidity;
    }

    protected function validateDuplicate()
    {
        foreach ($this->assessDuplications() as $index => $group) {
            if (count($group) != count(array_unique($group))) {
                lugWarning('Duplicate item', [$group]);
                $this->addError($this->transMessage('duplicate', [implode('+', $this->getUniqueItems()[$index])]), $this->messageKey());
            }
        }
    }

    protected function getUniqueItems() /// re written for locals
    {
        $data = [[$this->passengersForm['all']['first_name'][0]['caption'], $this->passengersForm['all']['last_name'][0]['caption']]];
        if (!empty($this->passengersForm['all']['nationalId'])) {
            $data[] = [$this->passengersForm['all']['nationalId'][0]['caption']];
        }
        if (!empty($this->passengersForm['all']['passport_number'])) {
            $data[] = [$this->passengersForm['all']['passport_number'][0]['caption']];
        }
        return $data;
        return [['National Id'], ['Passport Number'], ['First_name', 'Last_name']];
    }
}

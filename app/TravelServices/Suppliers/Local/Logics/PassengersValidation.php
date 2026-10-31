<?php

namespace App\TravelServices\Suppliers\Local\Logics;

trait PassengersValidation
{
    protected function leadPassengerValidation($passenger, $passengersForm)
    {
        return $this->allPassengersValidation($passenger, $passengersForm);
    }

    protected function allPassengersValidation($passenger, $passengersForm) /// Visa doesn't use this, make it do
    {
        foreach ($passengersForm as $itemType => $itemGroup) {
            if (in_array($itemType, ['photo', 'passport_number', 'text', 'text_area'])) {
                foreach ($itemGroup as $item) {
                    $this->validateRequired($passenger, $item['caption']);
                }
            }
            if (in_array($itemType, ['nationality', 'passport_country', 'country'])) {
                foreach ($itemGroup as $item) {
                    $this->validateCountry($passenger, $item['caption']);
                }
            }
            if (in_array($itemType, ['first_name', 'last_name', 'english_text'])) {
                foreach ($itemGroup as $item) {
                    $this->validateLatinNames($passenger, $item['caption']);
                }
            }
            if ($itemType == 'phone') {
                foreach ($itemGroup as $item) {
                    $this->validatePhone($passenger, $item['caption']);
                }
            }
            if ($itemType == 'birthdate') {
                foreach ($itemGroup as $item) {
                    $this->validateBirthdateByDeparture($passenger, $item['caption'], $this->ageRange()[0] ?? 0, $this->ageRange()[1] ?? 0);
                }
            }
            if ($itemType == 'age') {
                foreach ($itemGroup as $item) {
                    $this->validateAgeByDeparture($passenger, $item['caption'], $this->ageRange()[0] ?? 0, $this->ageRange()[1] ?? 0);
                }
            }
            if ($itemType == 'gender') {
                foreach ($itemGroup as $item) {
                    $this->validateGender($passenger, $item['caption']);
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
            if ($itemType == 'nationalId') {
                foreach ($itemGroup as $item) {
                    if (!empty($passengersForm['passport_number'])) {
                        $this->validateCodeMelliNotRequired($passenger, $item['caption']);
                    } else {
                        $this->validateCodeMelli($passenger, $item['caption']);
                    }
                }
            }
        }
        if (!empty($passengersForm['nationalId']) && !empty($passengersForm['passport_number'])) {
            ///> either national id or passport number should exist  
            $this->validatePassportNationalId($passenger);
        }
    }

    protected function getMinPassportValidityForLocals($passenger)
    {
        ///> this is so messy and dirty. will clean it up later. maybe seperate package's and tour's that might have multiple countries
        $this->passportExpiryData['Passport Validity by admin'] = null;
        $this->passportExpiryData['applied'] = $this->defaultMinPassportValidity;
        ///> get psngr's nationality id. it might not be entered in this attempt or not in the form at all; 
        $passengerNationality = $this->getPassengerCountryId($passenger);
        if (empty($passengerNationality)) {
            return $this->defaultMinPassportValidity;
        }

        ////> if there are multiple destination countries can't include it in the cache conditions (need to improve th ecache system's queries), so we get all the rules regarding psngr's nationality and then we'll check the destination countries
        $conditions = ['node_id' => node()->id, 'nationality_id' => $passengerNationality];
        $this->passportExpiryData['destinationCountries'] = $this->destinationCountries() ?? [];
        if (empty($this->passportExpiryData)) {
            return $this->defaultMinPassportValidity;
        }
        $cached = cachedDatabase('passport_expiry', [['destination_country_id', ':', array_column($this->passportExpiryData['destinationCountries'], 'id')]]);
        $this->passportExpiryData['Passport Validity by admin'] = $cached;
        if (empty($cached)) {
            return $this->defaultMinPassportValidity;
        }
        $this->passportExpiryData['Passport Validity by admin'] = $cached;
        $availableRules = collect($cached);
        foreach ($this->passportExpiryData['destinationCountries'] as $destinationCountry) {
            $month = $availableRules->where('destination_country_id', $destinationCountry['id'])->first()['months'] ?? $this->defaultMinPassportValidity;
            if (!empty($month)) {
                $months[] = $month;
            }
        }
        return  $this->passportExpiryData['applied'] = max($months ?? [$this->defaultMinPassportValidity]);
    }

    protected function getPassengerCountryId($passenger)
    {
        $passengerNationality = $this->getFromPassengerInfo($passenger, 'nationality');
        if (empty($passengerNationality)) {
            $passengerNationality = $this->getFromPassengerInfo($passenger, 'passport_country');
            if (empty($passengerNationality)) {
                if ($this->travelService == 'hotel') {
                    $passengerNationality = $this->queryParams['nationality_country']['id'] ?? false;
                }
                if ($this->travelService == 'visa') {
                    $passengerNationality = $this->queryParams['passport_country']['id'] ?? false;
                }
            }
        }
        return $passengerNationality ?? false;
    }

    protected function getFromPassengerInfo($passenger, $key)
    {
        $passengerNationality = $passenger[$this->getPassengerFormCaption($key)] ?? null;
        if (empty($passengerNationality)) {
            $passengerNationality = $passenger[$this->getPassengerFormCaption($key, 'lead')] ?? null;
        }
        return $passengerNationality ?? false;
    }
}

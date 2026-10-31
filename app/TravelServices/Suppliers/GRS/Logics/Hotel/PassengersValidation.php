<?php

namespace App\TravelServices\Suppliers\GRS\Logics\Hotel;

use App\TravelServices\Logics\Hotel\PassengersValidation as HotelPassengersValidation;

trait PassengersValidation
{
    use HotelPassengersValidation;

    protected function allPassengersValidation($guest)
    {
        $this->validateGender($guest, 'Gender');
        $this->validateRequired($guest, 'First_name');
        $this->validateRequired($guest, 'Last_name');
        $this->validateBirthdateByDeparture($guest, 'Date of birth', $this->ageRange()['max_child_age'] ?? 0, $this->ageRange()['max_infant_age'] ?? 0);
        $this->validateCodeMelliNotRequired($guest, 'National Id');

        $country = $this->validateCountry($guest, 'Nationality');
        $country = $country?->abb ?? null;
        if (empty($country)) return;
        if ($this->objectIsBooking) {
            $acceptable = $country == "IR" ? ['domestic', 'both'] : ['foreign', 'both'];
            foreach ($this->object->price_info['selectedCombination']['sellerPrice']['rawPrice'] as $rawRoomPInfo) {
                if (!in_array($rawRoomPInfo['nationality'], $acceptable)) {
                    $this->addError($this->transMessage('invalid', ['Nationality']), $this->messageKey('Nationality'));
                }
            }
        } else {
            if (($this->object->seller_info['passengerIsIranian'] && $country != "IR") || (!$this->object->seller_info['passengerIsIranian'] && $country == "IR")) {
                $this->addError($this->transMessage('invalid', ['Nationality']), $this->messageKey('Nationality'));
            }
        }

        ///> in GRS each room accepts either domestic, foreign or both type of guests
        ///> if room's nationality type is both room accepts any ntnlty, but in api-out we can't check that so for api-out we only allow all the guests to have the same ntnlty as the guest nationality that is inserted at search
    }

    protected function ageRange()
    {
        return $this->object->seller_info['children'];
    }

    protected function leadPassengerValidation($guest)
    {
        $this->validatePhone($guest, 'Phone');
        $this->validateEmail($guest, 'Email');

        ///> because io made nationalId and passport number optional in the passenger form but we want the leaser to have them
        if ($this->object->seller_info['passengerIsIranian']) {
            if (empty($guest['National Id'])) {
                $this->addError($this->transMessage('required', ['National Id']), $this->messageKey('National Id'));
            } else {
                $this->validateCodeMelli($guest, 'National Id');
            }
        } else {
            if (empty($guest['Passport Number'])) {
                $this->addError($this->transMessage('required', ['Passport Number']), $this->messageKey('Passport Number'));
            } else {
                $this->validateRequired($guest, 'Passport Number');
            }
        }
    }
}

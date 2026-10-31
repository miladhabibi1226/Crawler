<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics\Flight;

use App\TravelServices\Logics\Flight\PassengersValidation as CommonFlightPassengersValidation;

trait PassengersValidation
{
    use CommonFlightPassengersValidation;

    protected function allPassengersValidation($passenger)
    {
        $passenger['title'] = $this->validateGender($passenger, 'Gender');
        $this->validateLatinNames($passenger, 'First_name');
        $this->validateLatinNames($passenger, 'Last_name');
        $this->validateFullNameLenghth($passenger);

        ///> there is a RequiredFieldsToBook item that we get in the revalidate response. these are the passenger info items required for the book api that might be different for each airline
        ///> we'll be validating these items only if they exist in the RequiredFieldsToBook
        ///> we have called the revalidate api when geting the fareRules and stored the RequiredFieldsToBook items in the buffer's sellerInfo. fare Rules api is called before target search my marketplace
        $requiredPassengerInfo = $this->object->seller_info['validateResult']['RequiredFieldsToBook'];
        if (in_array('Passport', $requiredPassengerInfo) || in_array('Passport No', $requiredPassengerInfo)) {
            $this->validatePassportNumber($passenger, 'Passport Number');
            $this->validatePassportExpiry($passenger, 'Passport Expiry Date');
            $this->validateCountry($passenger, 'Passport Issuing Country');
        }
        if (in_array('DOB', $requiredPassengerInfo)) {
            $this->validateBirthdateByDeparture($passenger, 'Date of birth', 2, 12);
        }
        if (in_array('PassengerNationality', $requiredPassengerInfo)) {
            $this->validateCountry($passenger, 'Nationality');
        }
        if (in_array('NationalID', $requiredPassengerInfo)) {
            $this->validateRequired($passenger, 'National Id');
        }
    }

    protected function leadPassengerValidation($passenger, $key)
    {
        // $this->validateRequired($passenger, 'Post Code');
        $this->validatePhone($passenger, 'Phone');
        $this->validateEmail($passenger, 'Email');
    }

    protected function validateFullNameLenghth($passenger)
    {
        $fullname = ($passenger['title'] ?? "") . ($passenger['First_name'] ?? "") . ($passenger['Last_name'] ?? "");
        if (strlen($fullname) > $this->object->seller_info['validateResult']['PaxNameCharacterLimit'] ?? 150) {
            $this->addError($this->transMessage('passengerFullNameLength'), $this->messageKey('First_name'));
        }
    }

    //we rewrite this function for MystiFly because at least one of adults age should be 18 or more
    protected function extraValidation()
    {
        $adultsDob = [];
        $this->getLastDateOfTrip();
        foreach ($this->passengersInfo['adults'] as $adult) {
            if (isset($adult['Date of birth'])) {
                $adultsDob[] = $this->departure->diffInYears($adult['Date of birth']);
            }
        }
        //if $adultsDob is empty means there is no 'Date of birth' in passenger_Form
        if (empty($adultsDob)) {
            return true;
        }
        foreach ($adultsDob as $age) {
            if ($age >= 18) {
                return true;
            }
        }
        $this->addError($this->transMessage('adultsAge18'), $this->messageKey());
        return false;
    }
}

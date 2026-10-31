<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight;

use App\TravelServices\Logics\Flight\PassengersValidation as CommonFlightPassengersValidation;

trait PassengersValidation
{
    use CommonFlightPassengersValidation;

    protected function allPassengersValidation($passenger)
    {
        $this->validateGender($passenger, 'Gender');
        $this->validateLatinNames($passenger, 'First_name');
        $this->validateLatinNames($passenger, 'Last_name');
        $this->validateBirthdateByDeparture($passenger, 'Date of birth', 2, 12);
        $this->validateCountry($passenger, 'Nationality');
        $this->validatePassportExpiry($passenger, 'Passport Expiry Date');
        $this->validatePassportNumber($passenger, 'Passport Number', $this->validateCountry($passenger, 'Passport Issuing Country'));
    }

    protected function leadPassengerValidation($passenger)
    {
        $this->validateCity($passenger, 'City');
        $this->validatePhone($passenger, 'Phone');
        $this->validateEmail($passenger, 'Email');
    }

    protected function supplierNameTitle($gender)
    {
        // AirArabia accept MS and MR for adt and chd and inf
        // Chamwings accepts MS and MR for adt and MISS and MSTR for chd and inf
        $serviceInfo = $this->getServiceInfo();
        if (strtoupper($serviceInfo['validatingAirline']['supplier_key']) == 'G9' || $this->passengerData['type'] == 'adults') {
            $title = strtoupper($gender) == 'MALE' ? 'MR' : 'MS';;
        } else {
            $title = strtoupper($gender) == 'MALE' ? 'MSTR' : 'MISS';
        }
        return $title;
    }
}

<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\Old;

use App\TravelServices\Logics\Hotel\PassengersValidation as HotelPassengersValidation;

trait PassengersValidation
{
    use HotelPassengersValidation;

    protected function allPassengersValidation($guest)
    {
        $this->validateGender($guest, 'Gender');
        $this->validateNameLength($guest, 'First_name');
        $this->validateNameLength($guest, 'Last_name');
        $this->validateAge($guest, 'Age', 18);
    }

    protected function validateNameLength($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                if (!((strlen($passenger[$field]) >= 2) && (strlen($passenger[$field]) <= 20))) {
                    $this->addError($this->transMessage('roomNameLength', [$field]), $this->messageKey($field));
                } else {
                    $this->addToPassengerData(strtoupper(str_replace(' ', '', $passenger[$field])), $field);
                }
            }
        }
    }

    protected function supplierNameTitle($gender)
    {
        ///> TBO => Title of the guest (Possible values Mr, Mrs, Miss, Ms). Title for the child (Possible Values Mr,Ms)
        ///> we only accept Mr and Ms so
        if ($this->passengerData['typeSingular'] == 'adult') {
            $title = strtoupper($gender) == 'MALE' ? 'Mr' : 'Ms';
        } else {
            $title = strtoupper($gender) == 'MALE' ? 'Mr' : 'Mr';
        }
        return $title;
    }
}

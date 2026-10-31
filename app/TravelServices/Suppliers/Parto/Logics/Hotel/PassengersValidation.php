<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Hotel;

use App\TravelServices\Logics\Hotel\PassengersValidation as HotelPassengersValidation;

trait PassengersValidation
{
    use HotelPassengersValidation;

    protected function allPassengersValidation($guest)
    {
        $this->validateRequired($guest, 'First_name');
        $this->validateRequired($guest, 'Last_name');
        $this->validateAge($guest, 'Age', 18);
    }

    protected function leadPassengerValidation($passenger, $key)
    {
        $this->validatePhone($passenger, 'Phone');
        $this->validateEmail($passenger, 'Email');
    }

    protected function validateEmail($passenger, $field)
    {
        if ($this->validateRequired($passenger, $field)) {
            if (!empty($passenger[$field])) {
                if (!filter_var($passenger[$field], FILTER_VALIDATE_EMAIL)) {
                    $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                } else {
                    $emailUser = explode('@', $passenger[$field])[0];
                    if (strlen($emailUser) <= 3) {
                        $this->addError($this->transMessage('invalid', [$field]), $this->messageKey($field));
                    }
                }
            }
        }
    }
}

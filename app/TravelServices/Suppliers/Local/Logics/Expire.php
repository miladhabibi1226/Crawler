<?php

namespace App\TravelServices\Suppliers\Local\Logics;

trait Expire
{
    protected function canBeDone()
    {
        if ($this->object->status != 'on_hold') {
            $this->addError('Invalid Booking status: ' . $this->object->status);
        }
        return true;
    }
}

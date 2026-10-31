<?php

namespace App\TravelServices\Suppliers\DummyName\Logics\DummyTravelService;

use App\TravelServices\Handler;

trait BookAndIssue
{
    use Book;

    public function done()
    {
        return Handler::issue($this->object->reference_id);
    }
}

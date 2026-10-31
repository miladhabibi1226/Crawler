<?php

namespace App\TravelServices\Suppliers\Travelport\Logics\Flight;

use App\TravelServices\Handler;

trait BookAndIssue
{
    use Book;

    public function done()
    {
        $this->object->refresh();
        return Handler::issue($this->object->reference_id);
    }
}

<?php

namespace App\TravelServices\Suppliers\Faranegar\Logics\Flight;

use App\TravelServices\Handler;

trait BookAndIssue
{
    use Book;

    ///> when book done then go to issue
    public function done()
    {
        $this->object->refresh();
        return Handler::issue($this->object->reference_id);
    }
}

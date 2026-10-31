<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics\Flight;

use App\TravelServices\Handler;

trait BookAndIssue
{
    use Book;

    public function done()
    {
        request()->merge(['action' => 'confirm', 'travelService' => 'flight']);
        $confirm = Handler::afterBook($this->object->reference_id);
        if (!$confirm['status']) {
            return $confirm;
        }
        $this->object->refresh();
        return Handler::issue($this->object->reference_id);
    }
}

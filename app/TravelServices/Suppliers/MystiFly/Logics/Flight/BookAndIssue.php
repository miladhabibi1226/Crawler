<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics\Flight;

use App\TravelServices\Handler;

trait BookAndIssue
{
    use Book;

    public function done()
    {
        $this->object->refresh();
        if ($this->object->seller_info['validateResult']['HoldAllowed'] === 'True') {
            $bookingData = Handler::issue($this->object->reference_id);
            $this->object->refresh();
        }
        request()->merge(['action' => 'tripDetails', 'travelService' => 'flight']);
        return Handler::afterBook($this->object->reference_id);
    }
}

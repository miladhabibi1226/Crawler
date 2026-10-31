<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Flight;

use App\TravelServices\Handler;

trait BookAndIssue
{
    use Book;

    public function done()
    {
        request()->merge(['action' => 'bookingData', 'travelService' => 'flight']);
        $bookingData = Handler::afterBook($this->object->reference_id);
        if (!$bookingData['status']) {
            return $bookingData;
        }
        $this->object->refresh();
        if (in_array($this->object->seller_info['bookingResult']['partoStatus'], [10, 11])) {
            $bookingData = Handler::issue($this->object->reference_id);
        }
        $this->object->refresh();
        if (in_array($this->object->seller_info['bookingResult']['partoStatus'], [20, 21])){
            $this->sellerTransaction();
        }
        return $bookingData;
    }
}

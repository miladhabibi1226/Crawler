<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

use App\TravelServices\Handler;

trait BookAndIssue
{
    use Issue;
    public function canBeDone()
    {
        $bookResponse = Handler::book($this->object->reference_id);
        $this->object->refresh();
        if (!$bookResponse['status']) {
            ///> failBook() happens in the book class IF necessary
            $this->addError('Booking failed');
            return false;
        }
        request()->merge(['action' => 'PNRRetrieve', 'travelService' => 'flight']);
        $afterBookResponse = Handler::afterBook($this->object->reference_id);
        if (!$afterBookResponse['status']) {
            $this->addError('PNRRetrieve failed');
            $this->failIssue();
            return false;
        }

        $this->supplierSession = $this->retrieveSession('PNRRetrieve');
        if (empty($this->supplierSession)) {
            $this->addError('Session Expired.');
            $this->failIssue();
            return false;
        }
        return true;
    }
}

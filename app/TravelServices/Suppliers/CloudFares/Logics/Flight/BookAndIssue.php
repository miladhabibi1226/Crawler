<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

use App\TravelServices\Handler;

trait BookAndIssue
{
    use Book;
    public function done()
    {
        $this->object->refresh();
        $issue = Handler::issue($this->object->reference_id);
        if (!$issue['status']) {
            return $issue;
        }
        request()->merge(['action' => 'retriveBook', 'travelService' => 'flight']);
        return Handler::afterBook($this->object->reference_id);
    }
}

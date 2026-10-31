<?php

namespace App\TravelServices\Logics\Hotel;

use App\TravelServices\Logics\Book as LogicsBook;
use Carbon\Carbon;

trait Book
{
    use LogicsBook;
    protected $goingToCallIssue = false;

    protected function getExpiryTime()
    {
        ///> expiry is important for bookOnly
        ///> before bookOnly, when creating the book, we check that all the rooms have free cancelation policy
        ///> so at this point we are sure that all the rooms have it, so we need to return the earliest cancelation deadline among the rooms
        $allDeadlines = array_column(array_column($this->object->service_info['selectedCombination'], 'cancelationPolicies'), 'lastCancellationDeadline');
        return min($allDeadlines);
    }

    protected function updateExtraInfo()
    {
    }
}

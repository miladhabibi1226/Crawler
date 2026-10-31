<?php

namespace App\TravelServices\Logics;

trait BookAndIssue
{
    protected function issuePending()
    {
        $this->object->status = 'on_hold';
        $this->object->update();
        sendPendingIssueEmails($this->object);
    }
}


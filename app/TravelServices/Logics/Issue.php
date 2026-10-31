<?php

namespace App\TravelServices\Logics;

use Carbon\Carbon;

trait Issue
{
    ///> the suppliers who don't have seperate book and issue will not use this. like avtra and sepehr
    public function canBeDone()
    {
        if ($this->bookingType == 'bookOnly') {
            if (Carbon::create($this->object->expire_on) < Carbon::now()) {
                $this->object->status = 'expired';
                $this->object->update();
                $this->addError('The booking has been expired');
                return false;
            }
        }

        if ($this->needsSessionHandling() && !$this->sessionHandling()) {
            $this->addError('Unable to Get Session');
            $this->addErrorToBook(trans('messages.session'));
            $this->failIssue();
            return false;
        }
        return true;
    }

    protected function getContent() ///> local suppliers will not be calling getContent at all
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Faranegar/FlightIssue.json'));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                $this->failIssue();
                return false;
            }
        }

        $this->decodeRawResult();
        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            $this->failIssue();
            return false;
        }
    }

    protected function decodeRawResult() ///> json suppliers use this (except Nira), XML suppliers and Nira rewrite this
    {
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
    }

    public function failIssue()
    {
        if ($this->seller->reserve_when_issue_fails) {
            $this->object->status = 'incomplete';
            $this->object->update();
            sendPendingIssueEmails($this->object);
        } else {
            $this->object->status = 'failed';
            $this->object->update();
        }
    }

    protected function done() ///> at least amadeus MystiFly rewrite this
    {
        $this->sellerTransaction();
        return ['status' => true];
    }

    protected function needsSessionHandling()
    {
        return in_array($this->supplierName, ['CityNet', 'FlyErbil', 'ITours', 'Parto', 'Sabre', 'BasisFly', 'MystiFly']);
    }
}

<?php

namespace App\TravelServices\Logics;

use Carbon\Carbon;

trait Book
{
    ///> Accelaero and Sepehr don't use this bc they don't have a Book api at all, only BookAndIssue
    ///> getContent and canBeDone are used by some BookAndIssue/Issue classes

    public function canBeDone()
    {
        if (!$this->checkStartStatus()) {
            $this->addError('Invalid Booking status: ' . $this->object->status);
            $this->addErrorToBook(trans('messages.bookingStatus'));
            return false;
        }

        if ($this->onHoldAllowed()) {
            $this->addError('Can not on-hold this item');
            lugWarning('Can not onhold this item', ['bookingType' => $this->bookingType, 'onholdable' => $this->object->seller_info]);
            $this->failBook();
            return false;
        }

        ////>>>>    CHCEK TO MAKE SURE THE BEFOREBOOK ACTIONS THAT WAS SUPPOSED TO BE CALLED BY MARKETPLACE ARE CALLED

        if ($this->hasBeforeBookActions() && !$this->performBeforeBookActions()) {
            ///> some suppliers have before book actions that needs to be called here (like amadeus)
            $this->addError('Before Book Actions Failed');
            $this->failBook();
            return false;
        }

        if ($this->needsSessionHandling() && !$this->sessionHandling()) {
            $this->addError('Unable to Get Session');
            $this->addErrorToBook(trans('messages.session'));
            $this->failBook();
            return false;
        }
    }

    protected function getContent() ///> local suppliers don't use this
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path($this->responseSample));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->requestGeneratorFailed();
                return false;
            }
        }

        $this->decodeRawResult();

        if ($this->haveSupplierResponseError()) {
            $this->handleSupplierError();
        }
    }

    protected function mapper()
    {
        if ($this->supplierIsLocal()) {
            $this->updateLocalItems();
        }
        $this->object->seller_info = array_merge($this->object->seller_info ?? [], $this->items['sellerInfo'] ?? []);
        $this->object->booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingInfo'] ?? []);
        $this->object->booked_at = Carbon::now();
        if ($this->bookingType == 'bookOnly') {
            $this->object->status = 'on_hold';
            $expireOn = $this->checkExpiryTime(Carbon::create($this->items['bookingInfo']['expires_at']));
            $this->object->expire_on = $expireOn;
        }
        if (!empty($this->items['bookingInfo']['pnr'][0])) {
            $this->object->pnr = implode(',', (array) $this->items['bookingInfo']['pnr']) . ':';
        }
        $this->object->update();
        $this->handleSignOut();
    }

    protected function handleSupplierError()
    {
        if ($this->needsUrgentInvestigation()) {
            $this->contactAdminStatus();
            $this->addError("Invalid supplier response status");
            return false;
        }
        $this->failBook();
        $this->addError("Supplier has error");
        return false;
    }

    protected function needsUrgentInvestigation() ///> parto
    {
        return in_array($this->rawResult['http_code'] ?? 500, [502, 524, 504]);
    }

    protected function checkStartStatus()
    {
        return $this->object->status == 'validated';
    }

    protected function onHoldAllowed()
    {
        return  $this->bookingType == 'bookOnly' && $this->object->seller_info['onholdable'] == false;
    }

    protected function decodeRawResult() ///> json suppliers use this, XML suppliers rewrite this
    {
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
    }

    protected function hasBeforeBookActions()
    {
        return in_array($this->supplierName, ['Amadeus', 'Navitaire', 'FlyAdeal']);
    }

    protected function needsSessionHandling()
    {
        return !in_array($this->supplierName, ['Local', 'Quantum', 'Avtra', 'TBO', 'GRS', 'Faranegar', 'Nira', 'Sepehran', 'Travelport']);
    }

    protected function supplierIsLocal()
    {
        return $this->supplierName == 'Local';
    }

    protected function handleSignOut() ///> only Amadeus and FlyErbil rewrite it
    {}

    protected function requestGeneratorFailed() ///> rewritten for MystiFly
    {
        $this->addError("Supplier connection error");
        $this->failBook();
    }
}

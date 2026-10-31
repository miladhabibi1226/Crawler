<?php

namespace App\TravelServices\Logics;

trait BeforeBook
{
    ///> NOT used in amadeus, sabre
    public function canBeDone()
    {
        if (!$this->checkStartStatus()) {
            $this->addError('Invalid Booking status: ' . $this->object->status);
            $this->addErrorToBook(trans('messages.bookingStatus'));
            return false;
        }

        if ($this->onHoldNotAllowed()) {
            $this->addError('Can not on-hold this item');
            lugWarning('Can not onhold this item', ['bookingType' => $this->bookingType, 'onholdable' => $this->object->seller_info]);
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

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path($this->responseSample));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                $this->addErrorToBook("Supplier connection error");
                $this->failBook();
                return false;
            }
        }

        $this->decodeRawResult();

        if ($this->haveSupplierResponseError()) {
            $this->failBook();
            $this->addError("Supplier has error");
            return false;
        }
    }

    protected function mapper() {}

    protected function checkStartStatus()
    {
        return !$this->objectIsBooking || $this->object->status == 'validated';
    }

    protected function onHoldNotAllowed()
    {
        return  $this->bookingType == 'bookOnly' && $this->object->seller_info['onholdable'] == false;
    }

    protected function decodeRawResult() ///> json suppliers use this, XML suppliers rewrite this
    {
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
    }

    protected function needsSessionHandling()
    {
        return !in_array($this->supplierName, ['Local', 'Quantum', 'Avtra', 'GRS', 'Faranegar', 'Nira', 'Travelport']);
    }

    protected function supplierIsLocal()
    {
        return $this->supplierName == 'Local';
    }

    protected function handleSignOut() ///> FlyAdeal Navitaire rewrite it
    {}

    protected function failed($error = null)
    {
        $this->handleSignOut();
        $this->expireSupplierCache();
        $err = [];
        foreach ($error ?? [] as $errorMessages) {
            if ($this->getErrorClass($errorMessages) != 'technical') {
                $err[] = $errorMessages;
            }
        }
        if (empty($this->getSupplierErrorMessage())) {
            $error = implode('. ', $err);
        } else {
            $error = $this->getSupplierErrorMessage();
        }
        return ['status' => false, 'message' => $error];
    }
}

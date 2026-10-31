<?php

namespace App\TravelServices\Suppliers\Local\Logics\Package;

trait CheckAvailability
{
    use ItemValidityHelper;

    protected function getContent()
    {
        $this->getRawResult();
        lugInfo("Local Package CheckAvailability getContent items", $this->rawResult ?? []);
    }

    protected function extractor()
    {
        $this->checkAvailabilityAndPricing();
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['beforeBooks']['checkAvailability'] = true;
        $this->object->seller_info = $sellerInfo;
        $this->object->update();
    }

    protected function failed($error = null)
    {
        if ($this->objectIsBooking) {
            $this->failBook();
        }
        return ['status' => false];
    }
}

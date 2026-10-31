<?php

namespace App\TravelServices\Logics\Flight;

trait Captcha
{
    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/' . $this->supplierName . '/FlightCaptcha.json'));
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function extractor()
    {
        $captchaLink = $this->getCaptcha($this->rawResult['decoded']);
        if (empty($captchaLink)) {
            lugError('empty captchaResult', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            return $this->addError('empty result');
        }
        $this->items = [
            'captchaLink' => $captchaLink
        ];
        lugInfo($this->supplierName . ' Flight captcha extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['passengersForm']['captcha'] = ['link' => $this->items['captchaLink']];
        $sellerinfo['afters']['captcha'] = true;
        $this->object->seller_info = $sellerInfo;
        $this->object->update();
    }
}

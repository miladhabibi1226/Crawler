<?php

namespace App\TravelServices\Suppliers\CityNet\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'CityNet';
        $this->defaultHeader = ['Content-Type:application/json'];
        $this->sessionLifeTime = 30;
        $this->dataReference = types('supplierDataReference', 'CityNet');
        $this->supplierSearchValidity = 10;
    }

    public function captcha()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (CityNet-' . $this->travelService . ') -> Captcha', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (CityNet-' . $this->travelService . ') -> Captcha', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (CityNet-' . $this->travelService . ') -> Captcha');
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (CityNet-' . $this->travelService . ') -> Captcha', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (CityNet-' . $this->travelService . ') -> Captcha', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (CityNet-' . $this->travelService . ') -> Captcha', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        if (($this->rawResult['decoded']['Success'] ?? true) == false) {
            $err = [];
            $rawError = $this->rawResult['decoded']['Name'] ?? null;
            if (!empty($rawError)) {
                $err[] = $rawError;
            }
            $items = $this->rawResult['decoded']['Items'] ?? null;
            if (is_string($items)) {
                $err[] =  $items;
            } elseif (is_array($items)) {
                foreach ($items  as $Item) {
                    if (is_string($Item)) {
                        $err[] = $Item;
                    } elseif (!empty($Item['message'])) {
                        $err[] = $Item['message'];
                    } elseif (!empty($Item['messages'])) {
                        $err[] = implode('; ', $Item['messages']);
                    } elseif (is_string($Item['Items'] ?? [])) {
                        $err[] = $Item['Items'];
                    }
                }
                $err = implode('; ', $err);
            }
            $err = $err ?? 'Error message unavailable. See logs';
            lugWSResError("CityNet response contains error ({$this->seller->alias})", ['supplier errors: ' => $err, 'rawResult' => $this->rawResult]);
        } elseif (($this->rawResult['http_code'] ?? 200) != 200) {
            $err = "CityNet response is invalid: {$this->rawResult['http_code']} ({$this->seller->alias})";
            lugWSResError($err, ['rawResult' => $this->rawResult]);
        }


        $this->supplierConversaion($err ?? null);
        return $err ?? false;
        ///> rewritten in fareRules
    }

    protected function authorizeRequest()
    {
        if (empty($this->latestSession)) {
            $this->findOrCreateSupplierSession();
            $this->latestSession = $this->supplierSession?->extras['session'] ?? null;
            $this->defaultHeader[] = 'Authorization:Bearer ' . $this->latestSession;
        }
        return !empty($this->latestSession);
    }

    public function revalidate()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (CityNet-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (CityNet-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (CityNet-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (CityNet-Flight) -> Revalidate', ['Data' => $this->object->toArray()]);
        return $this->done();
    }
}

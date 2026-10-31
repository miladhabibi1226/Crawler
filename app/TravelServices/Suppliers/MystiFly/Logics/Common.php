<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;
    protected $sessionLifeTime;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'MystiFly';
        $this->defaultHeader = ['Content-Type:application/json'];
        $this->dataReference = types('supplierDataReference', 'MystiFly');
        $this->supplierSearchValidity = 20;
        $this->cacheAllowed = false; ///> by supplier's request
    }


    public function targetSearch()
    {
        ///> bc we don't cache this supplier we won't do target search (we don't cache bc we can't do targetSearch lol)
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (MystiFly-Flight) -> Target Search', ['Data' => $this->getErrors(), 'Object' => $this->object]);
            return  $this->failed();
        }
        lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> Target Search', ['Data' => $this->object]);
        return $this->done();
    }

    public function revalidate()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (MystiFly-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (MystiFly-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (MystiFly-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (MystiFly-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (MystiFly-Flight) -> Revalidate', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function TripDetails()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (MystiFly-Flight) -> Trip Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (MystiFly-Flight) -> Trip Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (MystiFly-Flight) -> Trip Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (MystiFly-Flight) -> Trip Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (MystiFly-Flight) -> Trip Details', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        $rawError = $this->rawResult['decoded']['Error'] ?? null;
        if (!empty($rawError)) {
            lugWSResError("MystiFly response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError["Message"] ?? $rawError);
            return $rawError["Message"] ?? $rawError;
        }
        if (($this->rawResult['decoded']['Data']['Success'] ?? true) != true) {
            $rawError = implode('; ', array_column((array)($this->rawResult['decoded']['Data']['Errors'] ?? []), 'Message'));
            lugWSResError("MystiFly response contains error ({$this->seller->alias})", [$this->rawResult['decoded']['Data']['Errors'] ?? [], array_column((array)($this->rawResult['decoded']['Errors'] ?? []), 'Message'), 'supplierError: ' => $rawError ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError["Message"] ?? $rawError);
            return $rawError["Message"] ?? $rawError;
        }
        if (($this->rawResult['decoded']['Success'] ?? false) != true) {
            $rawError = $this->rawResult['decoded']['Message'] ?? null;
            if (is_array($this->rawResult['decoded']['Data'] ?? [$rawError])) {
                $error = implode('; ', $this->rawResult['decoded']['Data'] ?? [$rawError]);
            } else {
                $error = $this->rawResult['decoded']['Data'];
            }
            lugWSResError("MystiFly response contains error ({$this->seller->alias})", ['supplierError: ' => $error ?? $rawError, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError["Message"] ?? $rawError);
            return $rawError["Message"] ?? $rawError;
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("MystiFly response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'MystiFly response is invalid';
        }
        $this->supplierConversaion();
        return false;
    }

    protected function authorizeRequest()
    {
        if (empty($this->latestSession)) {
            $this->findOrCreateSupplierSession();
            $this->latestSession = $this->supplierSession['session'] ?? null;
            $this->defaultHeader[] = 'Authorization:Bearer ' . $this->latestSession;
        }
        return !empty($this->latestSession);
    }
}

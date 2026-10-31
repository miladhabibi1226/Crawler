<?php

namespace App\TravelServices\Suppliers\TBO\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;

    protected $sessionLifeTime, $star;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'TBO';
        $this->dataReference = types('supplierDataReference', 'TBO');
        $this->defaultHeader = $licence->api->configs['version'] === "New" ? ['Content-Type: application/json'] : ['Content-Type: application/soap+xml; charset=utf-8'];
        $this->sessionLifeTime = 30;
        $this->supplierSearchValidity = $this->sessionLifeTime;
        $this->star = ['OneStar' => 1, 'TwoStar' => 2, 'ThreeStar' => 3, 'FourStar' => 4, 'FiveStar' => 5, 'All' => 0];
    }

    public function Detail()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (TBO-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (TBO-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (TBO-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (TBO-Hotel) -> Details');
        return $this->done();
    }

    public function availableRooms()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (TBO-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (TBO-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (TBO-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (TBO-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (TBO-Hotel) -> - Available Rooms');
        return $this->done();
    }

    public function availabilityAndPricing()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (TBO-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (TBO-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (TBO-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (TBO-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (TBO-Hotel) -> Availablity And Pricing', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function getRoomInformation()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (TBO-Hotel) -> Get Room Information', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (TBO-Hotel) -> Get Room Information', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (TBO-Hotel) -> Get Room Information', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (TBO-Hotel) -> Get Room Information', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (TBO-Hotel) -> Get Room Information', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        preg_match('/<StatusCode>(.*?)<\/StatusCode><Description>(.*?)<\/Description>/ims', $this->rawResult['body'], $rawErrors);
        if (intval($rawErrors[1] ?? 0) != 1) {
            lugWSResError("TBO response contain error ({$this->seller->alias})", $this->lugInfo + ['supplier-error: ' => $rawErrors[2] ?? '', 'rawErrors' => $rawErrors, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawErrors[2] ?? "supplier-error");
            return $rawErrors[2] ?? "supplier-error";
        }
        $this->supplierConversaion();
        return false;
    }

    protected function haveSupplierResponseErrorNew()
    {
        $responseStatus = $this->rawResult['decoded']['Status'] ?? null;
        lugInfo('$this->rawResult',[$this->rawResult,$responseStatus ]);
        if ($responseStatus['Code'] !== 200) {
            lugWSResError("TBO New Version response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($responseStatus['Description'] ?? $responseStatus);
            return $rawError["Description"] ?? $responseStatus;
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("TBO New Version response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'TBO New Version response is invalid';
        }
        $this->supplierConversaion();
        return false;
    }
}

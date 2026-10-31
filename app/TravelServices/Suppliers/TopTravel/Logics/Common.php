<?php

namespace App\TravelServices\Suppliers\TopTravel\Logics;

use Carbon\Carbon;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;
    protected $sessionLifeTime;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $auth = $this->seller->api->configs;
        $this->supplierName = 'TopTravel';
        $this->defaultHeader = [];
        $this->dataReference = types('supplierDataReference', 'TopTravel');
        $this->sessionLifeTime = 20;
    }

    public function fareConfirm()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (TopTravel-Flight) -> Fare Confirm', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (TopTravel-Flight) -> Fare Confirm', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (TopTravel-Flight) -> Fare Confirm', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (TopTravel-Flight) -> Fare Confirm', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (TopTravel-Flight) -> Fare Confirm', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function Recheck()
    {

        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (TopTravel-Flight) -> Recheck', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (TopTravel-Flight) -> Recheck', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (TopTravel-Flight) -> Recheck', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (TopTravel-Flight) -> Recheck', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (TopTravel-Flight) -> Recheck', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function payment()
    {

        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (TopTravel-Flight) -> Payment', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (TopTravel-Flight) -> Payment', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (TopTravel-Flight) -> Payment', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (TopTravel-Flight) -> Payment', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (TopTravel-Flight) -> Payment', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function bookingStatus()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (TopTravel-Flight) -> - test', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (TopTravel-Flight) -> test', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (TopTravel-Flight) -> test', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (TopTravel-Flight) -> test', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (TopTravel-Flight) -> test');
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        if (!empty($this->rawResult['decoded']['error']) || !empty($this->rawResult['decoded']['errors'])) {
            $error =  $this->rawResult['decoded']['error'] ?? '';
            $errorMassege = $this->rawResult['decoded']['message'] ?? '';
            lugWSResError("TopTravel response contains error ({$this->seller->alias})", ['supplierError: ' => $errorMassege ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($errorMassege ?? $error);
            return $errorMassege  ?? $error;
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("TopTravel response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'TopTravel response is invalid';
        }
        $this->supplierConversaion();
        return false;
    }

    protected function extractResponse()
    {
        if ($this->queryParams['tripType'] === 'oneWay') {
            return $this->rawResult['decoded']['onwardFlightOptions'];
        } else {
            return $this->rawResult['decoded']['roundTripFlightOptions'];
        }
    }
}

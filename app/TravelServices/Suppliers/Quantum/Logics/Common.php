<?php

namespace App\TravelServices\Suppliers\Quantum\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Quantum';
        $this->dataReference = types('supplierDataReference', 'Quantum');
        $this->defaultHeader = [
            'Content-Type:application/json',
            'X-Auth-Token:' . $this->seller->api->configs['token'],
            'Origin: https://supplier.' . node()->domain,
            'pid:' . app('log-system')->getPid()
        ];
        $this->cacheAllowed = false;
    }

    public function availabilities()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Availabilites', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Availabilites', ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Availabilites', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Availabilites', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Availabilites');
        return $this->done();
    }

    public function passengersValidation()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Passengers Validation', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Passengers Validation', ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Passengers Validation', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Passengers Validation', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Passengers Validation', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function Detail()
    {
        if (!$this->canBeDone()) {
            lugInfo('Done: ' . $this->seller->alias . ' (Quantum-Hotel) -> Details');
            return $this->done();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (Quantum-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Quantum-Hotel) -> Details', ['Data' => $this->object->toArray()], ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Quantum-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Quantum-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Quantum-Hotel) -> Details');
        return $this->done();
    }

    public function availableRooms()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Quantum-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Quantum-Hotel) -> - Available Rooms', ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Quantum-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Quantum-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Quantum-Hotel) -> - Available Rooms');
        return $this->done();
    }

    public function bookAndIssue()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Book And Issue', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Book And Issue', ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Book And Issue', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Book And Issue', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Book And Issue', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function fareRules()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Quantum-Flight) -> Fare Rules', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Quantum-Flight) -> Fare Rules', ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Quantum-Flight) -> Fare Rules', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Quantum-Flight) -> Fare Rules', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Quantum-Flight) -> Fare Rules');
        return $this->done();
    }

    public function asyncTicketing()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (Quantum-Flight) -> Async Ticketing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Quantum-Flight) -> Async Ticketing', ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Quantum-Flight) -> Async Ticketing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Quantum-Flight) -> Async Ticketing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Quantum-Flight) -> Async Ticketing', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function RetrieveBookingData()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (Quantum-Flight) -> Retrieve Booking Data', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Quantum-Flight) -> Retrieve Booking Data', ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Quantum-Flight) -> Retrieve Booking Data', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Quantum-Flight) -> Retrieve Booking Data', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Quantum-Flight) -> Retrieve Booking Data', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function pay()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Pay', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Quantum-' . ucfirst($this->travelService) . ') -> Pay', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function captcha()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Quantum-' . $this->travelService . ') -> Captcha', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Quantum-' . $this->travelService . ') -> Captcha');
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Quantum-' . $this->travelService . ') -> Captcha', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Quantum-' . $this->travelService . ') -> Captcha', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Quantum-' . $this->travelService . ') -> Captcha', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function beforeBook()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Quantum) - BeforeBook)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Quantum) - BeforeBook)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Quantum) - BeforeBook)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Quantum) - BeforeBook)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Quantum) - BeforeBook)');
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        preg_match('/^pid: (\w+)/', $this->rawResult['body'], $pid);
        if (!empty($pid)) {
            lugWSResError("Quantum response contains error ({$this->seller->alias})", ['supplierResponse: ' => $pid, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($pid[1] ?? '');
            return "Error Happened. PID: " . $pid[1] ?? '';
        }
        $rawError = $this->rawResult['decoded']['error'] ?? null;
        if (!empty($rawError)) {
            preg_match('/Supplier response:(.*?)\z/', $rawError, $output_array);
            $Error = ltrim($output_array[1] ?? $rawError);
            lugWSResError("Quantum response contains error ({$this->seller->alias})", ['supplierError' => $Error, 'rawResult' => $this->rawResult['body']]);
            $this->supplierConversaion($Error);
            return $Error;
        }

        if ($this->responseStatusIsInvalid()) {
            lugWSResError("Quantum response has invalid response status ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $this->rawResult]);
            $this->supplierConversaion($rawError ?? 'invalid response status', ['status', ($this->rawResult['decoded']['status'] ?? '')]);
            return $rawError ?? 'invalid response ';
        }

        $this->supplierConversaion();
        return false;
    }

    protected function responseStatusIsInvalid()
    {
        return ($this->rawResult['decoded']['status'] ?? false) != true;
    }

    protected function workingWithACachedProfile()
    {
        return !empty($this->object->seller_info['cachedId']);
    }
}

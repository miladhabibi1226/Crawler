<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'CloudFares';
        $this->defaultHeader = ['Accept:application/json'];
        $this->dataReference = types('supplierDataReference', 'CloudFares');
        $this->cacheAllowed = false; ///> by supplier's request
        $this->supplierSearchValidity = 15;
    }

    public function targetSearch()
    {
        lugWarning('bc we dont cache this supplier we wont do target search');
        ///> bc we don't cache this supplier we won't do target search (we don't cache bc we can't do targetSearch lol)
        lugInfo('Done: ' . $this->seller->alias . ' (' . $this->supplierName . ' ' . ucfirst($this->travelService) . ') -> Target Search', ['Data' => $this->object]);
        return $this->done();
    }

    public function Price()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (CloudFares) - Validate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (CloudFares) - Validate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (CloudFares) - Validate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (CloudFares) - Validate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (CloudFares) - Validate)', ['object' => $this->object->toArray()]);
        return $this->done();
    }

    public function retriveBook()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (CloudFares-Flight) -> Booking Data', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (CloudFares-Flight) -> Booking Data', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (CloudFares-Flight) -> Booking Data', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (CloudFares-Flight) -> Booking Data', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (CloudFares-Flight) -> Booking Data', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    protected function haveSupplierResponseError($openReturnresult = null)
    {
        $rawResult = empty($openReturnresult) ? $this->rawResult : $openReturnresult;
        if (strtolower($rawResult['decoded']['message'] ?? '') !== "success") {
            $errors = $rawResult['decoded']['errors'] ?? null;
            lugWSResError("CloudFares response contains error ({$this->seller->alias})", ['supplierError' => $errors, 'supplier response: ' => $rawResult]);
            $this->supplierConversaion($errors ?? 'Error happened');
            return $errors ?? 'Error happened';
        }
        if ($rawResult['http_code'] != 200) {
            lugWSResError("CloudFares response is invalid ({$this->seller->alias})", ['rawResult' => $rawResult]);
            $this->supplierConversaion('invalid response status: ' . $rawResult['http_code']);
            return 'CloudFares response is invalid';
        }
        $this->supplierConversaion();
        return false;
    }

    protected function getFlightNumber($flight)
    {
        $serviceInfo = $this->getServiceInfo();
        foreach ($serviceInfo['legs'] as $legIndex => $leg) {
            foreach ($leg['segments'] as $segIndex => $segment) {
                $departure = $segment['departure']['airport']['abb'];
                $arrival = $segment['arrival']['airport']['abb'];
                if ("$departure - $arrival" == $flight['short_label']) {
                    return [$segment['flight_number'], $legIndex, $segIndex];
                }
            }
        }
    }
}

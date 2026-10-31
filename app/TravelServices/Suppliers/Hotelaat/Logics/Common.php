<?php

namespace App\TravelServices\Suppliers\Hotelaat\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Hotelaat';
        $this->dataReference = types('supplierDataReference', 'Hotelaat');
        $this->supplierSearchValidity = 15;
        $this->defaultHeader = ['Content-Type: application/json'];
    }

    public function canBedone()
    {
        $this->authorizeRequest();
    }

    public function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Hotelaat/HotelSearch.json'));
            $this->rawResult['http_code'] = 200;
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

    public function ping()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' Hotelaat - Ping)', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' Hotelaat - Ping)', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' Hotelaat - Ping)');
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' Hotelaat - Ping)', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' Hotelaat - Ping)', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' Hotelaat - Ping)', ['Seller' => $this->seller]);
        return $this->done();
    }

    public function signIn()
    {
        // lugInfo('Get Content: ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> SignIn', ['Data' => $this->object->toArray()]);
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> SignIn', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> SignIn');
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> SignIn', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Mapper: ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> SignIn');
        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> SignIn', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> SignIn', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function StartSearch()
    {
        // lugInfo('CanBeDone: ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> StartSearch', ['Data' => $this->object->toArray(), 'Configs' => $this->seller->api->configs]);
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> StartSearch', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        // lugInfo('Get Content: ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> StartSearch', ['Data' => $this->object->toArray()]);
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> StartSearch', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> StartSearch');
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> StartSearch', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> StartSearch', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function Detail()
    {
        // lugInfo('CanBeDone: ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> Details', ['Data' => $this->object->toArray(), 'Configs' => $this->seller->api->configs]);
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Hotelaat-' . $this->travelService . ') -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        // lugInfo('Get Content: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Details', ['Data' => $this->object->toArray()]);
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Details', ['Data' => $this->object->toArray()], ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Mapper: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Details');
        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Details', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function AvailableRooms()
    {
        // lugInfo('CanBeDone: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> - Available Rooms', ['Data' => $this->object->toArray()]);
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Hotelaat-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Get Content: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> - Available Rooms', ['Data' => $this->object->toArray()]);
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Hotelaat-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> - Available Rooms', ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Hotelaat-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Mapper: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> - Available Rooms');
        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Hotelaat-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> - Available Rooms', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function AvailabilityAndPricing()
    {
        // lugInfo('CanBeDone: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Availability And Pricing', ['Data' => $this->object->toArray()]);
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Get Content: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Availablity And Pricing', ['Data' => $this->object->toArray()]);
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Extractor: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Availablity And Pricing', ['rawResult' => $this->rawResult['decoded'] ?? []]);
        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        // lugInfo('Mapper: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Availablity And Pricing');
        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Hotelaat-Hotel) -> Availablity And Pricing', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        $rawError = $this->rawResult['decoded']['error'] ?? false;
        if ($rawError) {
            lugWSResError("Hotelaat response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $this->rawResult['decoded'], 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError ?? $rawError);
            return $this->rawResult['decoded']['message'] ?? 'Error Happen';
        }
        $this->supplierConversaion();
        return false;
    }

    protected function authorizeRequest()
    {
        if (empty($this->latestSession)) {
            $this->findOrCreateSupplierSession();
            $this->latestSession = $this->supplierSession?->extras['supplierSession'] ?? null;
            $this->defaultHeader[] = 'Authorization: Bearer ' . $this->latestSession;
        }
        return !empty($this->latestSession);
    }
}

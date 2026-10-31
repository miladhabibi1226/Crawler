<?php

namespace App\TravelServices\Suppliers\Parto\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;

    protected $sessionLifeTime;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Parto';
        $this->defaultHeader = ['Content-Type:application/json'];
        $this->dataReference = types('supplierDataReference', 'Parto');
        $this->sessionLifeTime = 15;
        $this->supplierSearchValidity = 10;
    }

    public function ping()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Parto) - Ping)', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Parto) - Ping)', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Parto) - Ping)', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Parto) - Ping)', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Parto) - Ping)', ['Seller' => $this->seller]);
        return $this->done();
    }

    public function availableRooms()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Parto-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Parto-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Parto-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Parto-Hotel) -> - Available Rooms', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Parto-Hotel) -> - Available Rooms');
        return $this->done();
    }

    public function availabilityAndPricing()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Parto-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (Parto-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Parto-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Parto-Hotel) -> Availability And Pricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Parto-Hotel) -> Availablity And Pricing', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function revalidate()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Parto-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Parto-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Parto-Flight) -> Revalidate', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Parto-Flight) -> Revalidate', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function BookingData()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Parto-Flight) -> Booking Data', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Parto-Flight) -> Booking Data', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Parto-Flight) -> Booking Data', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Parto-Flight) -> Booking Data', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Parto-Flight) -> Booking Data', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function Detail()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Parto-Hotel) -> - Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get Content ' . $this->seller->alias . ' (Parto-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Parto-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Parto-Hotel) -> Details', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Parto-Hotel) -> Details');
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        $rawError = $this->rawResult['decoded']['Error'] ?? null;
        if (!empty($rawError)) {
            lugWSResError("Parto response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError["Message"] ?? $rawError);
            return $rawError["Message"] ?? $rawError;
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("Parto response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'Parto response is invalid';
        }
        $this->supplierConversaion();
        return false;
    }

    protected function isDomesticHotel()
    {
        return $this->queryParams['destination_city']['country']['abb'] == 'IR';
    }

    protected function getPassengerFormCaption($type, $allOrLead = 'all', $itemNumber = 0)
    {
        if ($type == 'nationality') {
            return  'Passport Issuing Country';
        }
        return Parent::getPassengerFormCaption($type, $allOrLead, $itemNumber);
    }

    protected function checkForPriceChange($ItinTotalFare)
    {
        ///> currently we will fail the booking if the price has changed
        $searchTotalPrice = $this->object->price_info['sellerPrice']['rawPrice']['ItinTotalFare']['TotalFare'];
        if (!$this->fromFile && $ItinTotalFare['TotalFare'] != $searchTotalPrice) {
            lugError('Price Change', ['search' => $this->object->price_info['sellerPrice']['rawPrice'], 'result' => $ItinTotalFare]);
            $this->addError("Price has changed");
            if ($this->objectIsBooking) {
                $this->failBook();
                $this->addErrorToBook(trans('messages.priceChanged'));
            }
        }
    }
}

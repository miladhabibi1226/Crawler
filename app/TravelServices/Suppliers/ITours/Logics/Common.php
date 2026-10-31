<?php

namespace App\TravelServices\Suppliers\ITours\Logics;

use App\TravelServices\Logics\Common as LogicsCommon;
use Carbon\Carbon;

trait Common
{
    use LogicsCommon, Errors;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'ITours';
        $this->defaultHeader = [
            "Content-Type:application/json",
            "accept:application/json",
        ];
        $this->dataReference = types('supplierDataReference', 'ITours');
        $this->supplierSearchValidity = 20;
    }

    public function validateFlight()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (ITours-Flight) -> Validate Flight', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Get Content ' . $this->seller->alias . ' (ITours-Flight) -> Validate Flight', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Extractor ' . $this->seller->alias . ' (ITours-Flight) -> Validate Flight', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Mapper ' . $this->seller->alias . ' (ITours-Flight) -> Validate Flight', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (ITours-Flight) -> Validate Flight', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function getFlightReserve()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Get Content ' . $this->seller->alias . ' (ITours-Flight) -> - Get Flight Reserve', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Extractor ' . $this->seller->alias . ' (ITours-Flight) -> - Get Flight Reserve', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Mapper ' . $this->seller->alias . ' (ITours-Flight) -> - Get Flight Reserve', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (ITours-Flight) -> - Get Flight Reserve');
        return $this->done();
    }

    public function availability()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (ITours-Flight) -> Availability', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Get Content ' . $this->seller->alias . ' (ITours-Flight) -> - Availability', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Extractor ' . $this->seller->alias . ' (ITours-Flight) -> - Availability', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (ITours-Flight) -> - Availability');
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        $rawError = $this->rawResult['decoded']['errors'] ?? null;
        if (!empty($rawError)) {
            $err = ($this->rawResult['decoded']['title'] ?? 'See Response') . '. traceId: ' . ($this->rawResult['decoded']['traceId'] ?? '-');
            lugWSResError("ITours response contains error ({$this->seller->alias})", ['supplier response: ' => $this->rawResult['decoded']]);
        } else {
            $rawError = $this->rawResult['decoded']['error'] ?? null;
            if (!empty($rawError)) {
                $err = $rawError['message'] ?? 'Look At Response';
                lugWSResError("ITours response contains error ({$this->seller->alias})", ['supplier response: ' => $this->rawResult['decoded']]);
            } elseif ($this->rawResult['http_code'] != 200) {
                $err = "ITours response is invalid: {$this->rawResult['http_code']} ({$this->seller->alias})";
                lugWSResError($err, ['rawResult' => $this->rawResult]);
            }
        }

        $this->supplierConversaion($err ?? null);
        return $err ?? false;
        ///> re-written in search
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

    protected function checkForPriceChange($totalPrice)
    {
        ///> currently we will fail the booking if the price has changed
        $searchTotalPrice = $this->object->price_info['sellerPrice']['rawPrice']['itinTotalFare']['totalFare'];
        if (!$this->fromFile && $totalPrice != $searchTotalPrice) {
            lugError('Price Change', ['search' => $this->object->price_info['sellerPrice']['rawPrice'], 'result' => $totalPrice]);
            $this->addError("Price Has Changed");
            if ($this->objectIsBooking) {
                $this->failBook();
                $this->addErrorToBook(trans('messages.priceChanged'));
            }
        }
    }

    protected function checkTicket($ticketNumber)
    {
        ///> sometimes altho the response is successful but the tickets are not
        ///> immediately available, they will be provided by itours' team later,
        ///> so we mark the bookings that don't have ticket so we can run a schedule to retry getting the tickets
        ///> if only one of passengers don't have ticket number we mark this
        ///> we have been told that it will take at most 30 minutes so just to be sure we
        ///> will run scheduler only until 1 hour after booking

        $timeLimit = Carbon::create($this->object->booked_at)->addHour();
        if (empty($ticketNumber ?? null) && Carbon::now() < $timeLimit) {
            $this->needsSchedule = true;
            $this->objectStatus = 'waiting_issue';
        }
    }

    protected function chcekReserveStatus($reserveStatus)
    {
        if (in_array($this->action, ['issue', 'asyncTicketing']) && in_array($reserveStatus, ['WebServiceUnsuccessful', 'InProgress', 'UnConfirmed'])) {
            ///> * ITours:  If you did not get success status at confirm method response (if you get : WebServiceUnsuccessful , InProgress, UnConfirmed  ) you should check with our support team to make sure about the status of reservation before refunding the amount of reservation to your customer.
            $this->needsSchedule = true;
            $this->objectStatus = 'waiting_issue';
            return lugError("reserveStatus: $reserveStatus");
        }

        if (in_array($reserveStatus, ['Canceled', 'WebServiceCancel', 'WebServiceUnsuccessful', 'Refunded', 'Voided', 'PaidBack'])) {
            $this->failBook();
            $this->addError("reserveStatus: $reserveStatus");
        }
    }
}

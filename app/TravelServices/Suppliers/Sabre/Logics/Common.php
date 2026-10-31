<?php

namespace App\TravelServices\Suppliers\Sabre\Logics;

use App\Models\SupplierSession;
use App\TravelServices\Handler;
use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;

    protected $sessionLifeTime;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Sabre';
        $this->sessionLifeTime = 10080;
        $this->dataReference = types('supplierDataReference', 'Sabre');
        $this->defaultHeader = [
            "Content-Type:application/json",
            "accept:application/json",
        ];
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Sabre/FlightSearch.json'));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        $this->decodeRawResult();
        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    protected function decodeRawResult() ///> json apis use this, XML apis rewrite this
    {
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
    }

    public function ping()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Sabre) - Ping)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Sabre) - Ping)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Sabre) - Ping)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Sabre) - Ping)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Sabre) - Ping)', ['Seller' => $this->seller]);
        return $this->done();
    }

    public function revalidateFlight()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Sabre) - Revalidate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Sabre) - Revalidate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Sabre) - Revalidate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Sabre) - Revalidate)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Sabre) - Revalidate)');
        return $this->done();
    }

    protected function authorizeRequest()
    {
        if (empty($this->latestSession)) {
            $this->findOrCreateSupplierSession();
            $this->latestSession = $this->supplierSession?->session ?? 'null';
            $this->defaultHeader[] = 'Authorization:Bearer ' . $this->latestSession;
        }
        return $this->latestSession;
    }

    public function findOrCreateSupplierSession($type = 'rest')
    {
        $activeSession = SupplierSession::whereSearcherIdentity($type)->where('seller_id', $this->seller->id)->first();
        if (!empty($activeSession)) {
            $this->supplierSession = $activeSession;
            $this->latestSession = $this->supplierSession->session;
        } else {
            request()->merge(['sessionType' => $type]);
            $signIn = Handler::signIn($this->seller);
            if (!$signIn['status'] ?? false) {
                return false;
            }
            $this->supplierSession = $signIn['supplierSession'];
            $this->latestSession = $this->supplierSession->session;
        }
        return true;
    }

    protected function checkForPriceChange($totalPrice)
    {
        ///> currently we will fail the booking if the price has changed
        $searchAmount = $this->object->price_info['sellerPrice']['rawPrice']['totalFare']['totalPrice'] ?? 0;
        if (round(floatval($searchAmount), 5) != round(floatval($totalPrice), 5)) {
            lugError('Price Change', ['search' => $searchAmount, 'result' => $totalPrice, $this->object->price_info['sellerPrice']['rawPrice']]);
            $this->addError("Price Has Changed");
            $this->failBook();
            $this->addErrorToBook(trans('messages.priceChanged'));
        }
    }

    protected function haveSupplierResponseError()
    {
        if (!$this->fromFile) {
            $rawError = $this->rawResult['decoded']['error'] ?? null;
            if (!empty($rawError)) {
                lugWSResError("Sabre response contains error ({$this->seller->alias})", ['supplierError: ' => $this->rawResult['decoded']['error_description'] ?? $rawError, 'supplier response: ' => $this->rawResult['body']]);
                $this->supplierConversaion($this->rawResult['decoded']['error_description'] ?? $rawError);
                return $this->rawResult['decoded']['error_description'] ?? $rawError;
            }
            $rawError = $this->rawResult['decoded']['errorCode'] ?? null;
            if (!empty($rawError)) {
                lugWSResError("Sabre response contains error ({$this->seller->alias})", ['supplierError: ' => json_decode($this->rawResult['decoded']['message'] ?? "", true) ?? $rawError, 'supplier response: ' => $this->rawResult['body']]);
                $this->supplierConversaion($rawError);
                return $rawError;
            }
            ///> for book / issue apis   use regex
            $rawErrors = $this->rawResult['decoded']['CreatePassengerNameRecordRS']['ApplicationResults']['Error'] ?? ($this->rawResult['decoded']['AirTicketRS']['ApplicationResults']['Error']  ?? null);
            if (!empty($rawErrors)) {
                $rawError = '';
                foreach ($rawErrors as $Error) {
                    foreach ($Error['SystemSpecificResults'] ?? [] as $SystemSpecificResults) {
                        foreach ($SystemSpecificResults['Message'] ?? [] as $Message) {
                            $rawError .= $Message['content'];
                        }
                    }
                }
                lugWSResError("Sabre response contains error ({$this->seller->alias})", ['supplierError: ' => empty($rawError) ? $this->rawResult['decoded']['CreatePassengerNameRecordRS']['ApplicationResults'] : $rawError]);
                $this->supplierConversaion($this->rawResult['decoded']['error_description'] ?? $rawError);
                return $this->rawResult['decoded']['error_description'] ?? $rawError;
            }
            if ($this->rawResult['http_code'] != 200) {
                lugWSResError("Sabre response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
                $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
                return 'Sabre response is invalid';
            }
            $this->supplierConversaion();
            return false;
        }
    }
}

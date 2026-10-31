<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics;

use App\Models\SupplierSession;
use App\TravelServices\Logics\Common as LogicsCommon;
use Carbon\Carbon;

trait Common
{
    use LogicsCommon, Errors;

    protected $sessionLifeTime, $soapActions;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Amadeus';
        $this->defaultHeader = ['Content-Type:text/xml; charset=utf-8', 'SOAPAction:' . $this->soapAction()];
        $this->dataReference = types('supplierDataReference', 'Amadeus');
        $this->sessionLifeTime = 15;
        $this->supplierSearchValidity = 15;
    }

    protected function getContent() ///> this is NOT used in the BOOK class
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Amadeus/' . ucfirst($this->action) . '.xml'));
            $this->rawResult['http_code'] = 200;
            $this->latestSession = 'fromFile';
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                $this->ifBookingThenFail();
                return false;
            }
        }
        $this->decodeRawResult();
        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            $this->ifBookingThenFail();
            return false;
        }
        $this->increaseSessionSeqNum(); ///> for Book class this happens in Extractor
    }

    protected function decodeRawResult()
    {
        $xml = preg_replace('/<(\/?)(\w+):(\w+)/', '<$1$3', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($xml);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//Body')), true)[0] ?? [];
        $this->rawResult['header'] = json_decode(json_encode($xml->xpath('//Header')), true)[0] ?? [];
    }

    public function fareInformativePricing()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Amadeus-Flight) -> fareInformativePricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Amadeus-Flight) -> fareInformativePricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Amadeus-Flight) -> fareInformativePricing', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Amadeus-Flight) -> fareInformativePricing', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function sellFromRec()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Amadeus-Flight) -> Sell From Rec', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Amadeus-Flight) -> Sell From Rec', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Amadeus-Flight) -> Sell From Rec', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Amadeus-Flight) -> Sell From Rec');
        return $this->done();
    }

    public function addPNRElements()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Amadeus-Flight) -> Add PNR Elements', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Amadeus-Flight) -> Add PNR Elements', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Amadeus-Flight) -> Add PNR Elements', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Amadeus-Flight) -> Add PNR Elements', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Amadeus-Flight) -> Add PNR Elements');
        return $this->done();
    }

    public function createFOP()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Amadeus-Flight) -> Create FOP', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Amadeus-Flight) -> Create FOP', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Amadeus-Flight) -> Create FOP', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Amadeus-Flight) -> Create FOP', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Amadeus-Flight) -> Create FOP');
        return $this->done();
    }

    public function createTST()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Amadeus-Flight) -> Create TST', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Amadeus-Flight) -> Create TST', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Amadeus-Flight) -> Create TST', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Amadeus-Flight) -> Create TST', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Amadeus-Flight) -> Create TST');
        return $this->done();
    }

    public function pricePNR()
    {
        $this->canBeDone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Amadeus-Flight) -> Price PNR With Booking Class', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return  $this->failed();
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Amadeus-Flight) -> Price PNR With Booking Class', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Amadeus-Flight) -> Price PNR With Booking Class', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Amadeus-Flight) -> Price PNR With Booking Class', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Amadeus-Flight) -> Price PNR With Booking Class');
        return $this->done();
    }

    public function PNRRetrieve()
    {

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Amadeus-Flight) -> PNR Retrieve', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Amadeus-Flight) -> PNR Retrieve', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Amadeus-Flight) -> PNR Retrieve', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function AsyncTicketing()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Amadeus-Flight) -> Get Tickets', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Amadeus-Flight) -> Get Tickets', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Amadeus-Flight) -> Get Tickets', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Amadeus-Flight) -> Get Tickets', ['Data' => $this->object->toArray()]);
        return $this->done();
    }


    public function signOut()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Get Content ' . $this->seller->alias . ' (Amadeus-Flight) -> Sign Out', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: (Amadeus-Flight) Sign Out', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        preg_match('/<faultstring>(.*?)<\/faultstring>/', $this->rawResult['body'], $headerErrors);
        if (!empty($headerErrors)) {
            $rawError = $headerErrors[1] ?? 'Undetected error';
            lugWSResError("Amadeus response contain fault ({$this->seller->alias})", ['supplier-error: ' => $rawError, 'error: ' => $headerErrors, 'supplier-response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError);
            return $rawError;
        }
        preg_match('/<statusCode>X<\/statusCode>/', $this->rawResult['body'], $headerErrors);
        if (!empty($headerErrors)) {
            preg_match_all('/<freeText>(.*?)<\/freeText><\/errorWarningDescription>/', $this->rawResult['body'], $errors);
            $rawError = $errors[1][0] ?? 'Undetected error';
            lugWSResError("Amadeus response contain fault ({$this->seller->alias})", ['supplier-error: ' => $rawError, 'errors: ' => $errors,  'supplier-response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError);
            return $rawError;
        }
        preg_match('/errorCategory>EC<\/errorCategory/', $this->rawResult['body'], $errors);
        if (!empty($errors)) {
            $rawError = 'Undetected error';
            preg_match_all('/<errorSegment>(.*?)<\/errorSegment>/', $this->rawResult['body'], $errors);
            if (empty($errors[0])) {
                preg_match('/<errorWarningDescription>(.*?)<\/errorWarningDescription>/', $this->rawResult['body'], $errorWarningDescription);
                preg_match_all('/<freeText>(.*?)<\/freeText>/', $errorWarningDescription[1], $freeText);
                $rawError = array_map(fn ($item) => trim($item), $freeText[1] ?? []);
                $rawError = implode('; ', $rawError) ?? 'Undetected error';
            }
            lugWSResError("Amadeus response contain EC ({$this->seller->alias})", ['supplier-error: ' =>  $rawError,  'errors: ' =>  $errors, 'supplier-response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError);
            return $rawError;
        }
        $rawErrors = $this->rawResult['decoded']['Fare_MasterPricerTravelBoardSearchReply']['errorMessage'] ?? [];
        if (!empty($rawErrors)) {
            $errors = $rawErrors['errorMessageText'] ?? [];
            $this->repelSingleMode($errors);
            $errors = implode('; ', array_column($errors, 'description'));
            lugWSResError("Amadeus response contain error ({$this->seller->alias})", ['supplier-error: ' => $errors, 'supplier response: ' => $this->rawResult['decoded']]);
            $this->supplierConversaion($errors);
            return $errors;
        }
        if ($this->rawResult['http_code'] == 500) {
            lugWSResError("Amadeus response is invalid ({$this->seller->alias})", ['supplier-response: ' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'invalid response';
        }
        preg_match('/<!DOCTYPE html>/', $this->rawResult['body'], $rawError);
        if (!empty($rawError)) {
            lugWSResError("Amadeus response is invalid ({$this->seller->alias})", ['supplier-response: ' => $this->rawResult['body']]);
            $this->supplierConversaion('invalid response');
            return 'invalid response';
        }
        $this->supplierConversaion();
        return false;
    }

    protected function soapAction()
    {
        return [
            'Fare_MasterPricerTravelBoardSearch' => 'http://webservices.amadeus.com/FMPTBQ_21_4_1A',
            'Fare_InformativePricingWithoutPNR' => 'http://webservices.amadeus.com/TIPNRQ_18_1_1A',
            'MiniRule_GetFromRec' => 'http://webservices.amadeus.com/TMRXRQ_18_1_1A',
            'SingOut' => 'http://webservices.amadeus.com/VLSSOQ_04_1_1A',
            'Air_SellFromRecommendation' => 'http://webservices.amadeus.com/ITAREQ_05_2_IA',
            'PNR_AddMultiElements' => 'http://webservices.amadeus.com/PNRADD_21_1_1A',
            'FOP_CreateFormOfPayment' => 'http://webservices.amadeus.com/TFOPCQ_19_2_1A',
            'Fare_PricePNRWithBookingClass' => 'http://webservices.amadeus.com/TPCBRQ_18_1_1A',
            'Ticket_CreateTSTFromPricing' => 'http://webservices.amadeus.com/TAUTCQ_04_1_1A',
            'PNR_Retrieve' => 'http://webservices.amadeus.com/PNRRET_21_1_1A',
            'DocIssuance_IssueTicket' => 'http://webservices.amadeus.com/TTKTIQ_15_1_1A'
        ][$this->method ?? ''] ?? '';
    }

    protected function headerData()
    {
        $auth = $this->seller->api->configs;
        $url = $auth['url'];
        if ($url[strlen($url) - 1] != '\\') {
            $url .= "\\";
        }
        $password = $auth['password'];
        $nonce = random_bytes(32);
        $timestamp = date(DATE_ATOM);
        $encodedNonce = base64_encode($nonce);
        return $auth + [
            'action' => $this->soapAction(),
            'messageID' => createReferenceId(),
            'timestamp' => $timestamp,
            'nonce' => $encodedNonce,
            'passSHA' => base64_encode(sha1($nonce . $timestamp . sha1($password, true), true)),
            'api_url' => $auth['url'] . $auth['WSAP'],
        ];
    }

    protected function saveSession()
    {
        $extra = $this->rawResult['header']['Session'];
        $extra['SequenceNumber'] = ($extra['SequenceNumber'] ?? 1) + 1;
        $session =  SupplierSession::create([
            'session' =>  $this->action . $this->object->reference_id,
            'expire_on' => Carbon::now()->addMinutes($this->sessionLifeTime)->setTimezone('UTC'),
            'seller_id' => $this->seller->id,
            'extras' => json_encode(($extra ?? [])), ///> SequenceNumber => 2 , to be used in the next request
            'searcher_identity' => 'Fake_Searcher_Identity',
        ]);
        return $session;
    }

    protected function retrieveSession($action, $session = null)
    {
        return SupplierSession::whereSession($session ?? ($action . $this->object->reference_id))->first();
    }

    protected function increaseSessionSeqNum()
    {
        if (!empty($this->supplierSession)) {
            $extra = json_decode($this->supplierSession->extras, true);
            $extra['SequenceNumber'] = ($extra['SequenceNumber'] ?? 1) + 1;
            $this->supplierSession->extras = json_encode($extra);
            $this->supplierSession->update();
        }
    }

    protected function ifBookingThenFail() ///> re-written for PNRRetrieve and Issue
    {
        if ($this->objectIsBooking) {
            $this->failBook();
        }
    }
}

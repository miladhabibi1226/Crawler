<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

use App\TravelServices\Handler;
use App\TravelServices\Logics\Flight\Book as FlightBook;
use Carbon\Carbon;

trait Book
{
    use FlightBook;
    protected $method = 'PNR_AddMultiElements';
    protected $responseSample = 'Samples/Amadeus/FlightBook.xml';
    protected $commissionPercentage = 0;
    protected $forceComission = false;

    public function performBeforeBookActions()
    {
        request()->merge(['action' => 'createTST', 'travelService' => 'flight']);
        $createTST = Handler::beforeBook($this->object->reference_id);
        return $createTST['status'] ?? false;
    }

    public function sessionHandling()
    {
        $this->supplierSession = $this->retrieveSession('sellFromRec', $this->object->seller_info['sessions']['sellFromRec']);
        return !empty($this->supplierSession);
    }

    public function requestGenerator()
    {
        $depends = $this->headerData() + ['include' => 'Book', 'session' => json_decode($this->supplierSession->extras, true), 'percentage' => $this->commissionPercentage = $this->commissionPercentage()];
        $depends['forceComission']=$this->forceComission;
        lugInfo("Amadeus Book Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.inseries', $depends)->render(),
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    protected function decodeRawResult()
    {
        $xml = preg_replace('/<(\/?)(\w+):(\w+)/', '<$1$3', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($xml);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//Body')), true)[0] ?? [];
        $this->rawResult['header'] = json_decode(json_encode($xml->xpath('//Header')), true)[0] ?? [];
    }

    public function extractor()
    {
        $this->increaseSessionSeqNum();
        $result = $this->rawResult['decoded']['PNR_Reply'] ?? [];
        $pnrHeader = $result['pnrHeader'] ?? [];
        if (empty($pnrHeader['reservationInfo'])) {
            lugError('no pnrHeader reservationInfo', [$this->rawResult]);
            $this->addError('no pnrHeader reservationInfo');
            $this->failBook();
            return false;
        }
        $this->items['bookingInfo'] = [
            'pnr' => [$pnrHeader['reservationInfo']['reservation']['controlNumber'] ?? null],
            'expires_at' =>  $this->object->seller_info['expires_at'] ?? Carbon::now()->addHour()
        ];
        $this->items['sellerInfo'] = [
            'commissionPercentage' => $this->commissionPercentage,
            'bookingResult' => ['reservation' => $pnrHeader['reservationInfo']['reservation']],
        ];

        lugInfo('Amadeus Book extractor Items', ['items' => $this->items]);
    }

    protected function failed($error = null)
    {
        $this->handleSignOut();
        return ['status' => false];
    }

    protected function handleSignOut()
    {
        $this->callSignOut($this->object->seller_info['sessions']['sellFromRec']);
    }

    protected function commissionPercentage()
    {
        $dataFromMSS = dataFromMSS('bookingComission', ['seller' => $this->seller->id, 'serviceInfo' => $this->object->service_info]);
        $this->forceComission = $dataFromMSS['valueSet'] ?? false; ///> put this to make sure to include 0 commission in the book request, if the 0 value was set by admin
        return $dataFromMSS['comission'] ?? 0;
    }
}

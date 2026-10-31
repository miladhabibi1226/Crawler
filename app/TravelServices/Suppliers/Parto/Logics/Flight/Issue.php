<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Flight;

use App\TravelServices\Logics\Issue as LogicsIssue;
use App\TravelServices\Handler;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $this->call = [
            'fields' => json_encode(['UniqueId' => $this->object->seller_info['bookingResult']['uniqueId'], 'SessionId' => $this->latestSession]),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/Rest/Air/AirOrderTicket",
            'apiMethod' => 'Air Order Ticket'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $this->items = $this->rawResult['decoded'];
        if (!$this->items['Success']) {
            $this->addError("Parto OrderTicket Unsuccesssful");
            $this->failIssue();
        }
        lugInfo('Parto Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $bookingInfo = $this->object->booking_info;
        $bookingInfo['issued_at'] = Carbon::now();
        $this->object->booking_info = $bookingInfo;
        $this->object->seller_info = array_merge($sellerInfo, $this->items, ['currentStep' => 'issue']);
        $this->object->update();
    }

    protected function done()
    {
        request()->merge(['action' => 'bookingData', 'travelService' => 'flight']);
        return Handler::afterBook($this->object->reference_id);
    }
}

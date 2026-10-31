<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics\Flight;

use App\TravelServices\Logics\Issue as LogicsIssue;
use App\TravelServices\Handler;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    public function sessionHandling()
    {
        return $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        luginfo("MystiFly Issue Data", ['sellerInfo' => $this->object->seller_info]);
        $this->call = [
            'fields' => json_encode(["Target" => "Test", 'UniqueId' => $this->object->seller_info['bookingResult']['UniqueID']]),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/v1/OrderTicket",
            'apiMethod' => 'OrderTicket'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $this->rawResult = $this->rawResult['decoded']['Data'];
        if (!$this->rawResult['Success']) {
            $this->addError("MystiFly OrderTicket Unsuccesssful");
            $this->failIssue();
        }
        lugInfo('MystiFly Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $bookingInfo = $this->object->booking_info;
        $bookingInfo['issued_at'] = Carbon::now();
        $this->object->booking_info = $bookingInfo;
        $this->object->update();
    }

    protected function done()
    {
        return ['status' => true];
    }
}

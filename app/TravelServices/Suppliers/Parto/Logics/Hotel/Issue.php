<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Hotel;

use App\TravelServices\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    protected $latestSession;

    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $UniqueId = $this->object->booking_info['pnr'];
        $this->call = [
            'fields' => json_encode(['SessionId' => $this->latestSession, 'UniqueId' => $UniqueId]),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . '/Rest/Hotel/HotelOrder',
            'timeout' => 20,
            'apiMethod' => 'issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $result = $this->rawResult['decoded'] ?? null;

        $this->items = [
            'Category' => $status = $result['Category'] ?? null,
            'Status' => $category = $result['Status'] ?? null
        ];
        lugInfo("HotelOrder status: $status, Category: $category");
        if (!in_array($status, [20]) || !in_array($category, [20])) {
            lugError('Unexpected status for HotelOrder', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError('Unexpected status for HotelOrder');
        }
        lugInfo('Parto Hotel Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info, ['issued_at' => Carbon::now()]);
        $this->object->seller_info = array_merge($this->object->seller_info, ['issueResult' => $this->items]);
        $this->object->status =  'issued';
        $this->object->update();
        $this->itemIssued($this->object->booking_info['pnr']);
    }
}

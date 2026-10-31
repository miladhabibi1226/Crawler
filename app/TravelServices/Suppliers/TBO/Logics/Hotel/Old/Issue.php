<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\Old;

use App\TravelServices\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;

    public function sessionHandling()
    {
        return $this->checkSession($this->object->seller_info['session']);
    }

    protected function decodeRawResult()
    {
        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->booking_info + $auth + ['pnr' => $this->object->booking_info['pnr']];
        lugInfo('Blade Data', [$depends]);
        $this->call = [
            'fields' => view('TBO.GenerateInvoice', $depends)->render(),
            'url' => $auth['url'],
            'apiMethod' => 'Generate Invoice'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $this->items = ['invoiceNumber' => $this->rawResult['body'][0]['GenerateInvoiceResponse']['InvoiceNo'] ?? $this->callException('empty response')];

        lugInfo('TBO Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $seller = $this->object->seller_info;
        $seller['bookingResult']['invoice'] = $this->items['invoiceNumber'];
        $seller['issued_at'] = Carbon::now();
        $this->object->seller_info = $seller;
        $this->object->status = 'issued';
        $this->object->update();
        $this->itemIssued($this->object->booking_info['pnr']);
    }
}

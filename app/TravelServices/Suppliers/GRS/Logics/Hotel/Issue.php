<?php

namespace App\TravelServices\Suppliers\GRS\Logics\Hotel;

use App\TravelServices\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    protected $latestSession;

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $this->call = [
            'fields' => json_encode(["confirmation_code" => $this->object->booking_info['pnr']]),
            'url' => $this->cleanUrlSlash($auth['url']) . '/v1/book',
            'cookies' => null,
            'apiMethod' => 'issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $Result = $this->rawResult['decoded']['value']['reserve'] ?? [];
        if (empty($Result)) {
            lugError('empty result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failIssue();
            return $this->addError('empty result');
        }

        $this->checkStatus($Result);
    }

    protected function checkStatus($Result)
    {
        $status = strtoupper($Result['status']);
        lugInfo('GRS status: ' . $status);
        if (!in_array($status, ['DEFINITE', 'BOOKED'])) {
            $this->addError('Booking status: ' . $status);
        }
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info, ['issued_at' => Carbon::now()]);
        $this->object->status =  'issued';
        $this->object->update();
        $this->itemIssued($this->object->booking_info['pnr']);
    }
}

<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

use App\TravelServices\Logics\Issue as LogicsIssue;
use App\TravelServices\Handler;

use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    protected $latestSession, $needsSchedule = false, $ticks = [];

    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + ['bookingInfo' => $this->object->booking_info] + $this->object->seller_info;
        lugInfo('Issue Data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/b2b/v1/flight/ticket',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'Issue'
        ];
        return $this->action();
    }

    protected function getFields($depends)
    {
        return [
            "pnr" => $depends['pnr'],
            "last_name" => $depends['lastName'],
        ];
    }

    protected function extractor()
    {
        $this->items = $this->rawResult['decoded'];
        lugInfo('CloudFares Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $bookingInfo = $this->object->booking_info;
        $bookingInfo['issued_at'] = Carbon::now();
        $this->object->booking_info = $bookingInfo;
        $this->object->update();
    }
}

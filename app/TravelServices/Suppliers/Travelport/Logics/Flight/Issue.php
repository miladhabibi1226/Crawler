<?php

namespace App\TravelServices\Suppliers\Travelport\Logics\Flight;

use App\TravelServices\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    protected $latestSession, $needsSchedule = false, $ticks = [];

    protected function canBeDone()
    {
        $this->failIssue();
        $this->addError("based on TravelPort suggestion we do workflow until book and they issue thickets themselfs");
        return false;
    }


    protected function getContent() ///> local suppliers will not be calling getContent at all
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/TravelPort/FlightTicket.xml'));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                $this->failIssue();
                return false;
            }
        }
        $this->rawResult['body'] = str_replace("\n", "", $this->rawResult['body']);
        $xml = preg_replace('/<(\/?)(\w+):(\w+)/', '<$1$3', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($xml);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//Body')), true)[0] ?? [];

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            $this->failIssue();
            return false;
        }
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + ['bookingInfo' => $this->object->booking_info] + $this->object->seller_info;
        lugInfo('Issue Data', $depends);
        $this->call = [
            'fields' => $fields = $this->minifyXML(view('Travelport.FlightTicket', $depends)->render()),
            'url' =>   $this->cleanUrlSlash($auth['url']),
            'headers' => [
                "Content-length: " . strlen($fields)
            ],
            'apiMethod' => 'Issue'
        ];
        return $this->action();
    }

    protected function extractor() {}

    protected function mapper() {}

    protected function getTickets($ticketResults) {}
}

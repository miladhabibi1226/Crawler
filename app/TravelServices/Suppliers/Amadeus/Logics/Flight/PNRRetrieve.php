<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

trait PNRRetrieve
{
    protected $method = 'PNR_Retrieve';

    public function requestGenerator()
    {
        $depends = $this->headerData() + $this->queryParams + ['include' => 'PNRRetrieve', 'bookingResult' => $this->object->seller_info['bookingResult']['reservation']];
        lugInfo("Amadeus PNRRetrieve Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.start', $depends)->render(),
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'apiMethod' => 'PNRRetrieve'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $this->supplierSession = $this->saveSession();
        ///> nothing to check
        ///> just had to call this to be able to get the DocIssuance_IssueTicketReply
    }

    protected function ifBookingThenFail()
    {
        $this->failIssue();
    }
}

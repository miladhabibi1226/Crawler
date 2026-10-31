<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

use App\TravelServices\Handler;
use App\TravelServices\Logics\Issue as LogicsIssue;

trait Issue
{
    use LogicsIssue;
    protected $ticks = [];
    protected $method = 'DocIssuance_IssueTicket';

    public function requestGenerator()
    {
        $depends = $this->headerData() + ['include' => 'DocIssuance', 'airline' => $this->object->service_info['validatingAirline']['abb'], 'session' => json_decode($this->supplierSession->extras, true)];
        lugInfo("Amadeus Issue Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.inseries', $depends)->render(),
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'apiMethod' => 'Issue'
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

    protected function extractor()
    {
        $result = $this->rawResult['decoded']['DocIssuance_IssueTicketReply'] ?? [];
        $processingStatus = $result['processingStatus'] ?? [];
        if (($processingStatus['statusCode'] ?? null) != "O") {
            $this->addError('invalid processingStatus');
            $this->failIssue();
        }
        lugInfo('Amadeus Issue extractor Items', ['rawResult' => $result]);
    }

    protected function getTickets($travelers)
    {
    }

    protected function mapper()
    {
    }

    protected function done()
    {
        request()->merge(['action' => 'AsyncTicketing', 'travelService' => 'flight']);
        Handler::afterBook($this->object->reference_id);
        $this->sellerTransaction();
        return ['status' => 'true'];
    }
}

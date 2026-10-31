<?php

namespace App\TravelServices\Suppliers\Nira\Logics\Flight;

use App\TravelServices\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    protected $latestSession, $needsSchedule = false, $ticks = [];

    protected function decodeRawResult() ///> json suppliers use this (except Nira), XML suppliers and Nira rewrite this
    {
        preg_match_all('/:"([^"]+)"/',trim($this->rawResult['body']),$this->rawResult['decoded']);
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs + ['bookingInfo' => $this->object->booking_info];
        lugInfo('Issue Data', $depends);
        $query = implode('&',[
            "Airline={$this->Airline}",
            "PNR={$depends['bookingInfo']['pnr'][0]}",
            "OfficeUser={$depends['officeUser']}",
            "OfficePass={$depends['officePass']}",
            "Email={$depends['passengerContactEmail']}"
        ]);

        $this->call = [
            'fields' => "",
            'url' => $this->cleanUrlSlash($depends['url']) . "/ws2/cgi-bin/NRSWEB.cgi/ETIssueJS?{$query}",
            'method' => "GET",
            'apiMethod' => 'Issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        preg_match_all('/:"([^"]+)"/',trim($this->rawResult['body']),$tickets);
        $this->objectStatus = 'issued';
        if (empty($tickets)) {
            $this->objectStatus = 'waiting_issue';
            $this->needsSchedule = true;
        }
        $tickets = array_diff(explode(PHP_EOL,trim($tickets[1][0])),array(""));
        $this->items['bookingInfo'] = [
            'pnr' => $this->object->booking_info['pnr'],
            'tickets' => $this->flightTicketInfo($this->getTickets($tickets ?? [])),
            'issued_at' =>  Carbon::now()
        ];

        lugInfo('Nira Flight Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->booking_info = $this->items['bookingInfo'];
        $this->object->status =  $this->objectStatus;
        $this->object->pnr = $this->items['bookingInfo']['pnr'];
        $this->object->update();
        $this->itemIssued($this->items['bookingInfo']['pnr']);
    }

    protected function getTickets($ticketResult)
    {
        $passengers = [];
        foreach ($ticketResult as $traveler) {
            $traveler = trim($traveler);
            // DUKE/PIPER=6482401665389
            if (empty($traveler)) {
                $this->objectStatus = 'waiting_issue';
                $this->needsSchedule = true;
            }
            $travellerInfo = explode('=', $traveler);
            $ticketNumber = $travellerInfo[1];
            $passengers[strtoupper(str_replace('/', '', $travellerInfo[0]))] = $ticketNumber;
        }
        return $passengers;
    }

    protected function haveSupplierResponseError()
    {
        $rawError = empty($this->rawResult['decoded']) ? true : false;
        if ($rawError) {
            lugWSResError("Nira response contains error ({$this->seller->alias})", ['supplierError: ' => $this->rawResult, 'supplier response: ' => $this->rawResult['body'], 'decoded' => $this->rawResult['decoded']]);
            $this->supplierConversaion($rawError ?? $rawError);
            return $this->rawResult['decoded']['AirReserve'][0]['Error'] ?? 'Error Happen';
        }
        $this->supplierConversaion();
        return false;
    }
}

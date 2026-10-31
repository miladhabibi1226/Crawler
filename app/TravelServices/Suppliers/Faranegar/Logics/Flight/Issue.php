<?php

namespace App\TravelServices\Suppliers\Faranegar\Logics\Flight;

use App\TravelServices\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    protected $latestSession, $needsSchedule = false, $ticks = [];

    public function requestGenerator()
    {
        // $this->LugTheResponse = 1;
        $auth = $this->seller->api->configs;
        $depends = $auth + ['bookingInfo' => $this->object->booking_info];
        lugInfo('Issue Data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($auth['url']) . '/Air_Issue',
            'apiMethod' => 'issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $ticketResult = $this->rawResult['decoded'];
        $this->objectStatus = 'issued';
        $this->items['bookingInfo'] = [
            'pnr' => [$ticketResult['AirReservation']['bookingReferenceID']['Id']],
            'tickets' => $this->flightTicketInfo($this->getTickets($ticketResult['AirReservation']['TravelerInfo'] ?? [])),
            'issued_at' =>  Carbon::now()
        ];
        $this->items['pnr'] = $ticketResult['AirReservation']['bookingReferenceID']['Id'] . ':' . implode(',', array_unique($this->ticks));
        if (empty($this->ticks)) {
            $this->objectStatus = 'waiting_issue';
        }
        lugInfo('Faranegar Flight Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info, $this->items['bookingInfo']);
        $this->object->status =  $this->objectStatus;
        $this->object->pnr = $this->items['pnr'];
        $this->object->update();
        $this->itemIssued($this->items['bookingInfo']['pnr']);
    }

    protected function getTickets($ticketResult)
    {
        $passengers = [];
        $legSegment = $this->getLegSegments();
        foreach ($ticketResult as $passenger) {
            $ticketNumber = [];
            foreach ($legSegment as $index => $segment) {
                $OriginDestination = str_replace('/', '', $index);
                foreach ($passenger['ETicketInfos'] as $tickets) {
                    if ($tickets['FlightSegmentCode'] == $OriginDestination) {
                        $ticketNumber[$segment['legIndex']][$segment['segmentIndex']] = $tickets['ETicketNo'] ?? '';
                        $this->ticks[] = $tickets['ETicketNo'];
                    }
                }
            }
            $passengers[strtoupper(str_replace(' ', '', $passenger['PersonName']['Surname'] . $passenger['PersonName']['GivenName']))] = $ticketNumber;
        }
        return $passengers;
    }

    protected function getFields($depends)
    {
        $fields = [
            "POS" => [
                "UserName" => $depends['username'],
                "Password" => $depends['password']
            ],
            "OTA_DemandTicketRQ" => [
                "PNR" => $depends['bookingInfo']['pnr'][0]
            ]
        ];
        return $fields;
    }
}

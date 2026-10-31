<?php

namespace App\TravelServices\Suppliers\BasisFly\Logics\Flight;

use App\TravelServices\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    protected $latestSession, $needsSchedule = false, $ticks = [];

    public function sessionHandling()
    {
        $this->checkSession($this->object->seller_info['latestSession']);
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + ['bookingInfo' => $this->object->booking_info];

        lugInfo('Issue Data', $depends);
        $this->call = [
            'fields' => json_encode(['ApiKey' => $auth['apiKey'], 'BasisFlyBookId' => $this->object->seller_info['bookingResult']['BasisFlyBookId'], 'SessionId' => $this->latestSession]),
            'url' =>   $this->cleanUrlSlash($auth['url']) . '/api-test/Flight_v2/IssueTicket',
            'apiMethod' => 'issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $ticketResult = $this->rawResult['decoded'] ?? [];
        $this->objectStatus = 'issued';
        if (empty($ticketResult['BookingReferenceID'])) {
            lugError('No PNR');
            $this->objectStatus = 'incomplete';
        }
        $pnr = $ticketResult['BookingReferenceID'] ?? null;
        $this->items['bookingInfo'] = [
            'pnr' => [$pnr],
            'tickets' => $this->flightTicketInfo($this->getTickets($ticketResult['Tickets'] ?? [])),
            'issued_at' =>  Carbon::now()
        ];
        $this->items['pnr'] = $pnr . ':' . implode(',', array_unique($this->ticks));
        if (empty($this->ticks[0])) {
            lugError('No thicket');
            $this->objectStatus = 'incomplete';
        }
        lugInfo('BasisFly Flight Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info, $this->items['bookingInfo']);
        $this->object->status =  $this->objectStatus;
        $this->object->pnr = $this->items['pnr'];
        $this->object->update();
        $this->itemIssued($this->items['bookingInfo']['pnr']);
    }

    protected function getTickets($ticketResults)
    {
        $ticketNumber = $passangerTickets = [];
        foreach ($ticketResults as $key => $ticketResult) {
            $travelerFirstName = strtoupper(str_replace(' ', '', $ticketResult['FirstName'] ?? ''));
            $travelerLastName = strtoupper(str_replace(' ', '', $ticketResult['LastName'] ?? ''));
            $this->ticks[] = $ticketNumberAll[] = $ticketResult['TicketNumber'] ?? [];
            $passangerTickets[$travelerLastName . $travelerFirstName][] = $ticketResult['TicketNumber'] ?? '';
        }

        foreach ($passangerTickets as $passangerName => $passangerTicketArray) {
            foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                foreach ($leg['segments'] as $segmentIndex => $segment) {

                    $ticketNumber[$legIndex][$segmentIndex] = $passangerTicketArray[$legIndex] ?? ($passangerTicketArray[0] ?? '');
                }
            }
            $passengers[$passangerName] = $ticketNumber;
        }
        return $passengers;
    }
}

<?php

namespace App\TravelServices\Suppliers\ITours\Logics\Flight;

use App\TravelServices\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    protected $latestSession, $needsSchedule = false, $ticks = [];

    public function sessionHandling()
    {
        return $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        $key = $this->object->seller_info['bookingRequestData']['reserveId'];
        // lugInfo('issue data', ['reserveId' => $key]);
        $this->call = [
            'fields' => json_encode(['reserveId' => $key]),
            'method' => 'POST',
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/Partners/Flight/V2/Confirm",
            'apiMethod' => 'Issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $issuePNRResult = $this->rawResult['decoded']['result'] ?? $this->callException('empty result');
        $this->objectStatus = 'issued';
        $this->chcekReserveStatus($issuePNRResult['reserveStatus']);
        if (empty($issuePNRResult['pnrCode'])) {
            lugError('No PNR');
            $this->objectStatus = 'waiting_issue';
            $this->needsSchedule = true;
        }
        $this->items['bookingInfo'] = [
            'pnr' => [$issuePNRResult['pnrCode']],
            'tickets' => $this->flightTicketInfo($this->getTickets($issuePNRResult['passengers'] ?? [])),
            'passengerCount' => count($issuePNRResult['passengers'])
        ];
        $this->items['issueResult'] = [
            'reserveStatus' => $issuePNRResult['reserveStatus'],
            'pnrCode' => $issuePNRResult['pnrCode']
        ];
        $this->items['pnr'] = $issuePNRResult['pnrCode'] . ':' . implode(',', array_unique($this->ticks));
        lugInfo("ITours Flight $this->action extractor Items", ['items' => $this->items]);
    }

    protected function getTickets($ticketResult)
    {
        $passengers = [];
        foreach ($ticketResult as $traveler) {
            if (empty($traveler['ticketNumber'])) {
                $this->objectStatus = 'waiting_issue';
                $this->needsSchedule = true;
            }
            $ticketNumber = [];
            foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                foreach ($leg['segments'] as $segmentIndex => $segment) { ///> ITours has one ticket for all the legs/segments
                    $ticketNumber[$legIndex][$segmentIndex] = $traveler['ticketNumber'] ?? '';
                }
                if (!empty($traveler['ticketNumber'])) {
                    $this->ticks[] = $traveler['ticketNumber'];
                }
            }
            $passengers[strtoupper(str_replace(' ', '', $traveler['lastName'] . $traveler['firstName']))] = $ticketNumber;
            $this->checkTicket($traveler['ticketNumber']);
        }
        return $passengers;
    }

    protected function mapper()
    {
        $booking_info = $this->object->booking_info;
        $seller_info = $this->object->seller_info + ['issueResult' => $this->items['issueResult']];
        $booking_info = array_merge($this->object->booking_info, $this->items['bookingInfo']);
        $booking_info['issued_at'] = Carbon::now();
        $this->object->booking_info = $booking_info;
        $this->object->seller_info = $seller_info;
        $this->object->pnr = $this->items['pnr'];
        $this->object->status = $this->objectStatus;
        $this->object->async_action = $this->needsSchedule;
        $this->object->update();
        $this->itemIssued($this->items['bookingInfo']['pnr']);
    }
}

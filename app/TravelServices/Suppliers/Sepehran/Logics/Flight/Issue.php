<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics\Flight;

use App\TravelServices\Logics\Issue as LogicsIssue;
use Carbon\Carbon;

trait Issue
{
    use LogicsIssue;
    protected $latestSession, $needsSchedule = false, $ticks = [];

    protected function canBeDone()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + ['bookingInfo' => $this->object->booking_info];
        lugInfo('Issue Data', $depends);
        $this->call = [
            'fields' => json_encode(['reference' => $this->object->seller_info['bookingResult']['refrence_id']]),
            'url' =>   $this->cleanUrlSlash($auth['url'])  . '/api/v2/flight/AirDemandTicket',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $ticketResult = $this->rawResult['decoded'] ?? [];
        $this->objectStatus = 'issued';
        if (empty($ticketResult['flights'][0]['pnr'])) {
            lugError('No PNR');
            $this->objectStatus = 'incomplete';
        }
        $this->items['bookingInfo'] = [
            'pnr' => $pnr = array_column($ticketResult['flights'] ?? [], "pnr"),
            'tickets' => $this->flightTicketInfo($this->getTickets($ticketResult['tickets'] ?? [], $ticketResult['flights'] ?? [])),
            'issued_at' =>  Carbon::now()
        ];
        $this->items['pnr'] = implode(',', $pnr) . ':' . implode(',', array_unique($this->ticks));
        if (empty($this->ticks[0])) {
            lugError('No thicket');
            $this->objectStatus = 'incomplete';
        }
        lugInfo('Sepehran Flight Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info, $this->items['bookingInfo']);
        $this->object->status =  $this->objectStatus;
        $this->object->pnr = $this->items['pnr'];
        $this->object->update();
        $this->itemIssued($this->items['bookingInfo']['pnr']);
    }

    protected function getTickets($ticketResults, $flightResults)
    {
        $legSegments = $this->getLegSegments();
        $passengers = [];
        $ticketNumber = [];
        $flights = collect($flightResults);
        foreach ($ticketResults as $key => $ticketResult) {
            ///> when Sepehran supplier response have different pnrs for each leg :
            $flightResult = $flights->where('pnr', $ticketResult['pnr'])->first();
            $travelerFirstName = strtoupper(str_replace(' ', '', $ticketResult['given_name'] ?? ''));
            $travelerLastName = strtoupper(str_replace(' ', '', $ticketResult['sure_name'] ?? ''));
            $this->ticks[] = $ticketNumberAll[] = $ticketResult['ticket_number'] ?? [];
            $segment = $legSegments[$flightResult['departure_airport'] . '/' . $flightResult['arrival_airport']];
            $passengers[$travelerLastName . $travelerFirstName][$segment['legIndex']][$segment['segmentIndex']] = $ticketResult['ticket_number'] ?? '';
        }
        return $passengers;
    }
}

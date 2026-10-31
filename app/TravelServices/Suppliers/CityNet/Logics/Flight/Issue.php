<?php

namespace App\TravelServices\Suppliers\CityNet\Logics\Flight;

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
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->seller_info + ['sellerCurrency' => $this->seller->currency->abb];
        lugInfo('Issue Data', $depends);
        $this->call = [
            'fields' => '',
            'method' => 'GET',
            'url' => $this->cleanUrlSlash($auth['url']) . '/flights/ticket?' . http_build_query($this->getFields($depends)),
            'apiMethod' => 'issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        ///> for return flights the response is different for normal items and oneway combination items
        ///> but we have filtered oneWay combination items so here we are only handling normal flights
        $ticketResult = $this->rawResult['decoded']['Items'][0] ?? [];
        $this->objectStatus = 'issued';
        if (($ticketResult['Success'] ?? false) == false) {
            lugError('Success is false', ['ticketResult' => $ticketResult]);
            $errors = $ticketResult['Items'] ?? '';
            sendSupplierIssueErrorNotification($this->object, $this->seller, $errors, $this->supplierName, $this->supplierConnection['method'] ?? '-');
            $this->failIssue();
            return $this->addError('Supplier has error');
        }
        $Item = $ticketResult['Items'] ?? [];
        $pnr = $Item['AirItinerary']['pnr'] ?? '';
        if ($this->isInProgress(($pnr ?? 'In Progress'))) {
            $this->objectStatus = 'waiting_issue';
        }
        $this->items['bookingInfo'] = [
            'pnr' => [$pnr],
            'tickets' => $this->flightTicketInfo($this->getTickets($Item['TravelerInfo']['AirTraveler'])),
            'issued_at' =>  Carbon::now()
        ];
        $this->items['pnr'] = $pnr . ':' . implode(',', array_unique(array_merge(...$this->ticks)));

        lugInfo('CityNet Flight Issue extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info, $this->items['bookingInfo']);
        $this->object->status = $this->objectStatus;
        $this->object->async_action = $this->objectStatus == 'waiting_issue';
        $this->object->pnr = $this->items['pnr'];
        $this->object->update();
        if ($this->objectStatus == 'issued') {
            $this->itemIssued($this->items['bookingInfo']['pnr']);
        }
    }

    protected function getTickets($AirTravelers)
    {
        $passengers = [];
        foreach ($AirTravelers as $passengerKey => $traveler) {
            $ticketNumber = [];
            $travelerFirstName = strtoupper(str_replace(' ', '', $traveler['PersonName']['GivenName'] ?? ''));
            $travelerLastName = strtoupper(str_replace(' ', '', $traveler['PersonName']['Surname'] ?? ''));
            $this->ticks[] = $ticketNumberAll = $traveler['TicketNumber'] ?? [];

            if (count($ticketNumberAll) == count($this->object->service_info['legs'])) {
                foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                    foreach ($leg['segments'] as $segmentIndex => $segment) {
                        $ticketNumber[$legIndex][$segmentIndex] = $ticketNumberAll[$legIndex] ?? ($ticketNumberAll[0] ?? '');
                        if ($this->isInProgress($ticketNumber[$legIndex][$segmentIndex] ?? 'In Progress')) {
                            $this->objectStatus = 'waiting_issue';
                        }
                    }
                }
            } else {
                ///> this way the tickets might end up in the wrong segment but there really isn't any other way since it is not mentioned in the response that each ticket belongs to which segment
                $ticketIndex = 0;
                foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                    foreach ($leg['segments'] as $segmentIndex => $segment) {
                        $ticketNumber[$legIndex][$segmentIndex] = $ticketNumberAll[$ticketIndex] ?? '';
                        $ticketIndex++;
                    }
                }
            }


            $passengers[$travelerLastName . $travelerFirstName] = $ticketNumber;
        }
        return $passengers;
    }

    protected function isInProgress($value)
    {
        return in_array(($value ?? 'In Progress'), ['In Progress', 'inprocess']);
    }

    protected function getFields($depends)
    {
        $fields = [
            'ContractNo' => $depends['issueRequestData']['ContractNo'],
            'Credit' => 'true',
            'Wallet' => 'true',
            'Currency' => $depends['sellerCurrency'],
        ];
        return $fields;
    }
}

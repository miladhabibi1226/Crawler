<?php

namespace App\TravelServices\Suppliers\Sabre\Logics\Flight;

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
        //get card information
        $cardInformation = request('cardInformation')['value'] ?? null;
        if (empty($cardInformation)) {
            lugWarning('empty card information ! ', [$cardInformation]);
        }
        $valueParts = explode(',', $cardInformation);
        $cardType = $valueParts[0] ?? '';
        $cardExpiryDate = $valueParts[1] ?? '';
        $cardNumber = $valueParts[2] ?? '';

        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->booking_info + [
            'ItineraryRef' => $this->object->seller_info['supplierReference'],
            'passengersGroup' => array_keys($this->object->passenger_info),
            'paymentTypeFinal' => empty($cardInformation) ? $auth['paymentType'] : (strtoupper($cardInformation) == 'CASH' ? '' : 'CREDIT CARD'),
            'cardType' => empty($cardInformation) ? $auth['ccType'] ?? '' : $cardType, 'cardExpiryDate' => empty($cardInformation) ? $auth['ccExpiry'] ?? '' : $cardExpiryDate, 'cardNumber' => empty($cardInformation) ? $auth['ccNumber'] ?? '' : $cardNumber
        ];
        lugInfo('Issue Data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($auth['url']) . '/v1.2.1/air/ticket',
            'apiMethod' => 'issue'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $ticketResult = $this->rawResult['decoded']['AirTicketRS'] ?? [];
        $travelersTickets = $ticketResult['Summary'] ?? [];
        if (count($travelersTickets) != array_sum($this->object->service_info['passengersCount'])) {
            ///> this is highly unlikely
            $this->objectStatus = 'contact_admin';
            lugError('summery count does not match', [$this->rawResult]);
        }
        $this->objectStatus = 'issued';
        $this->items['bookingInfo'] = [
            'pnr' => $pnr = $this->object->booking_info['pnr'],
            'tickets' => $this->flightTicketInfo($this->getTickets($travelersTickets)),
            'issued_at' =>  Carbon::now()
        ];
        $this->items['pnr'] = $pnr[0] . ':' . implode(',', array_unique($this->ticks));
        lugInfo('Sabre Flight Issue extractor Items', ['items' => $this->items]);
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
            preg_match('/(.*?)\s(MR|MSR|MS|MSTR|MISS)\z/', $traveler['FirstName'] ?? '', $output_array);
            $travelerFirstName = $output_array[1] ?? '';
            $travelerLastName = strtoupper(str_replace(' ', '', $traveler['LastName'] ?? ''));
            ///> in all the cases that i have seen, there is one ticket for all the segments for each passenger
            $this->ticks[] = $ticket = $traveler['DocumentNumber'] ?? '';
            foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                foreach ($leg['segments'] as $segmentIndex => $segment) {
                    $ticketNumber[$legIndex][$segmentIndex] = $ticket;
                }
            }
            $passengers[$travelerLastName . $travelerFirstName] = $ticketNumber;
        }
        return $passengers;
    }

    protected function getFields($depends)
    {
        $fields = [
            'AirTicketRQ' => [
                'DesignatePrinter' => [
                    'Printers' => [
                        'Ticket' => [
                            'CountryCode' => $depends['countryCode']
                        ],
                        'Hardcopy' => [
                            'LNIATA' => $depends['lniata']
                        ],
                    ],
                ],
                'Itinerary' => [
                    'ID' => $depends['ItineraryRef']
                ],
                'Ticketing' => [],
                "PostProcessing" => [
                    "EndTransaction" => ["Source" => ["ReceivedFrom" => str_contains($depends['url'], 'cert') ? "API TEST" : node()->title]],
                    "GhostTicketCheck" => ["waitInterval" => 1000, "numAttempts" => 3],
                    "TicketingInterval" => ["waitInterval" => 1000],
                    "acceptPriceChanges" => false
                ]
            ]
        ];
        foreach ($depends['passengersGroup'] as $index => $passengersGroup) {
            $Ticketing = ['PricingQualifiers' => ['PriceQuote' => [['Record' => [['Number' => $index + 1, 'Reissue' => false]]]]]];
            if ($depends['paymentTypeFinal'] == 'CREDIT CARD') {
                $Ticketing['FOP_Qualifiers'] = ['BasicFOP' => [
                    'CC_Info' => [
                        'PaymentCard' => ['Code' => $depends['cardType'], 'ExpireDate' =>  $depends['cardExpiryDate'], 'Number' => (int) $depends['cardNumber']]
                    ]
                ]];
            }
            $fields['AirTicketRQ']['Ticketing'][] = $Ticketing;
        }
        return $fields;
    }
}

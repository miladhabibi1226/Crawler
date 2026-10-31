<?php

namespace App\TravelServices\Suppliers\Avtra\Logics\Flight;

use Carbon\Carbon;

trait BookAndIssue
{
    use Book;
    protected $ticks = [];
    protected $responseSample = 'Samples/Avtra/FlightBookAndIssue.xml';
    ///> Issue uses extractor

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded']['AirReservation'] ?? null;

        if (empty($BookResult)) {
            lugError('empty AirReservation', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('empty AirReservation');
        }

        $passengerCount = array_sum($this->object->service_info['passengersCount']);
        $pnr = (string) $BookResult['BookingReferenceID']['@attributes']['ID'] ?? null;
        $ticketingStatus = $BookResult['BookingReferenceID']['@attributes']['Status'];        

        $this->items = [
            'pnr' => (array) $pnr,
            'supplierBookingStatus' => ['39' => 'CONFIRMED', '16' => 'CANCELLED', '1' => 'TTL'][$ticketingStatus],
            'passengerCount' => $passengerCount,
            'tickets' => $this->ticketExtractor($BookResult, $passengerCount),
            'issued_at' => $ticketingStatus == '39' ? Carbon::now() : null,
            'odOptions' => $odOptions[0] ?? null
        ];

        lugInfo("Avtra Flight $this->action extractor Items", ['items' => $this->items, 'ticks' => $this->ticks]);
    }

    protected function mapper()
    {
        $this->object->status = $this->items['supplierBookingStatus'] == 'TTL' ? 'on_hold' : 'issued';
        $this->object->booked_at = Carbon::now();

        $booking_info = $this->object->booking_info;
        $booking_info = array_merge($this->object->booking_info ?? [], $this->items);
        $this->object->booking_info = $booking_info;
        $this->object->pnr = implode(',', $this->items['pnr']) . ':';
        $this->object->update();

        if (($this->items['supplierBookingStatus'] ?? false) == 'CONFIRMED') {
            $this->sellerTransaction();
            $this->itemIssued($this->items['pnr']);
        }
    }

    protected function ticketExtractor($BookResult, $passengerCount)
    {
        $ticketResponse = $BookResult['Ticketing'] ?? [];
        $this->repelSingleMode($ticketResponse);
        foreach ($ticketResponse as $passengerTicket) {
            $ticketNumber = [];
            foreach ($this->object->passenger_info as $passengerType => $passengerTypeGroup) {
                foreach ($passengerTypeGroup as $passengerIndex => $passenger) {
                    if ($passenger['rph'] == $passengerTicket['@attributes']['TravelerRefNumber']) {
                        foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                            foreach ($leg['segments'] as $segmentIndex => $segment) { ///> Avtra has one ticket for all the legs/segments
                                $ticketNumber[$legIndex][$segmentIndex] = $passengerTicket['@attributes']['TicketDocumentNbr'] ?? '';
                                if (!empty($passengerTicket['@attributes']['TicketDocumentNbr'])) {
                                    $this->ticks[] = $passengerTicket['@attributes']['TicketDocumentNbr'];
                                }
                            }
                        }
                        $tickets[] = [
                            'passengerType' => $passengerType,
                            'passengerIndex' => $passengerIndex,
                            'ticket_number' => $ticketNumber
                        ];
                        continue 3;
                    }
                }
            }
        }
        if (count($tickets ?? []) != $passengerCount) {
            lugWarning('Ticket info extraction has a problem', ['passengerCount' => $passengerCount, 'result' => $BookResult, 'extracted info' => $tickets ?? []]);
        }
        return $tickets ?? [];
    }
}

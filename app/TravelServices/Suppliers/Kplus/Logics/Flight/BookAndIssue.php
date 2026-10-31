<?php

namespace App\TravelServices\Suppliers\Kplus\Logics\Flight;

use Carbon\Carbon;

trait BookAndIssue
{
    use Book;
    protected $responseSample = 'Samples/Kplus/FlightBookAndIssue.json';
    protected function booKandIssueItems($BookResult)
    {
        $this->objectStatus = 'issued';
        $this->items['bookingInfo']['tickets'] = $this->flightTicketInfo($this->getTickets($BookResult['FlightBookingList']));
        $this->items['bookingInfo']['issued_at'] = Carbon::now();
        if (empty($this->ticks[0])) {
            $this->objectStatus = 'incomplete';
        }
        $this->items['pnr'] = ':' . implode(',', array_unique($this->ticks));
        $this->items['pnr'] = implode(',', $this->items['bookingInfo']['pnr']) . ':' . implode(',', array_unique($this->ticks));
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info, $this->items['bookingInfo']);
        $this->object->seller_info = array_merge($this->object->seller_info, $this->items['sellerInfo'] ?? []);
        $this->object->status = $this->objectStatus;
        $this->object->pnr = $this->items['pnr'];
        $this->object->booked_at = Carbon::now();
        $this->object->update();
        if ($this->objectStatus == 'issued') {
            $this->itemIssued($this->items['bookingInfo']['pnr']);
        }
    }

    protected function checkSupplierBookingStatus($BookResult)
    {
        return ($BookResult['BookingStatusType'] ?? '') == '7';
    }

    protected function getTickets($FlightBookingList)
    {
        $hasTickets = $this->object->service_info['validatingAirline']['abb'] != 'FZ';
        $passengers = [];
        foreach ($FlightBookingList as $fblKey => $FlightBooking) {
            foreach ($FlightBooking['PaxList'] as $Pax) {
                $travelerFirstName = $Pax['Pax']['FirstName'];
                $travelerLastName = $Pax['Pax']['LastName'];
                $this->ticks[] = $ticket = !empty($Pax['TicketNumber']) ? $Pax['TicketNumber'] : ($hasTickets ? '' : ($this->items['bookingInfo']['pnr'][$fblKey] ?? ($this->items['bookingInfo']['pnr'][0] ?? '')));
                foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                    foreach ($leg['segments'] as $segmentIndex => $segment) {
                        $ticketNumber[$legIndex][$segmentIndex] = $ticket;
                    }
                }
                $passengers[$travelerLastName . $travelerFirstName] = $ticketNumber;
            }
        }
        return $passengers;
    }

    protected function done()
    {
        $this->sellerTransaction();
        return ['status' => 'true'];
    }
}

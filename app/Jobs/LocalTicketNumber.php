<?php

namespace App\Jobs;

use App\Jobs\Job;
use MatinUtils\NodeTracker\Models\Booking;
use MatinUtils\NodeTracker\Models\FlightAllocation;
use MatinUtils\NodeTracker\Models\FlightAvailability;

class LocalTicketNumber extends Job
{
    protected $passengers, $nodeId, $bookingId, $pid;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $nodeId, int $bookingId, string $pid = null)
    {
        $this->nodeId = $nodeId;
        $this->bookingId = $bookingId;
        $this->pid = $pid;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function operate()
    {
        if (!empty($this->pid)) {
            app('log-system')->setPid($this->pid);
        }
        switchNode($this->nodeId);
        $booking = Booking::find($this->bookingId);
        insertSupportRecord($booking->getSerial(), ['pid' => ['setFlightTicketNumber' => app('log-system')->getPid()]]);
        $this->{$booking->travel_service . 'Booking'}($booking);

        // app('log-system')->send();
    }

    protected function flightBooking($booking)
    {
        $availabilities = FlightAvailability::whereIn('id', $booking->seller_info['availabilities'])->get();
        foreach ($availabilities as $availability) {
            $latestEtickets[$availability->id] = $availability->latest_eticket + 1;
        }

        $ticks = $ticketinfo = [];
        foreach ($booking->booking_info['tickets'] as $key => $ticketInfoOG) {
            $ticket_number = [];
            $ticketinfo[$key] = $ticketInfoOG;
            foreach ($booking->price_info['sellerPrice']['rawPrice'] as $legIndex => $legPricings) {
                foreach ($legPricings as $segIndex => $segPricing) {
                    $ticket_number[$legIndex][$segIndex] = $latestEtickets[$segPricing['flight_availability_id']];
                    $ticks[] = $latestEtickets[$segPricing['flight_availability_id']] = $latestEtickets[$segPricing['flight_availability_id']] + 1;
                }
            }
            $ticketinfo[$key]["ticket_number"] = $ticket_number;
        }

        foreach ($availabilities as $availability) {
            $availability->latest_eticket = $latestEtickets[$availability->id];
            $availability->update();
        }

        $booking->pnr = $booking->booking_info['pnr'][0] . ':' . implode(',', $ticks);
        $booking->booking_info = array_merge($booking->booking_info, ['tickets' => $ticketinfo]);
        $booking->status = 'issued';
        $booking->update();
        $this->itemIssued($booking);
        // lugInfo('Ticket Issue Job Done', ['booking' => $booking->toArray(), 'availabilities' => $availabilities]);
    }

    protected function itemIssued($booking)
    {
        $invoiceItem = $booking->invoiceItems->first();
        $description = $invoiceItem->description;
        $pnr = $booking->booking_info['pnr'];
        $pnr = implode(',', (array) $pnr);
        $description .= ", PNR:$pnr";
        $passengers = $this->flightPax($booking);
        $description .= " <br>$passengers";
        $invoiceItem->description = $description;
        $invoiceItem->update();
    }

    protected function flightPax($booking)
    {
        $passengers = [];
        $firstNameCaption = $booking->seller_info['passengersForm']['all']['first_name'][0]['caption'];
        $lastNameCaption = $booking->seller_info['passengersForm']['all']['last_name'][0]['caption'];
        foreach ($booking->booking_info['tickets'] as $passenger) {
            $et = implode(',', array_map(fn ($arr) => implode(',', array_unique($arr)), $passenger['ticket_number']));
            $firstNmae = strtoupper($booking->passenger_info[$passenger['passengerType']][$passenger['passengerIndex']][$firstNameCaption]);
            $lastNmae = strtoupper($booking->passenger_info[$passenger['passengerType']][$passenger['passengerIndex']][$lastNameCaption]);
            $passengers[] =  "$lastNmae $firstNmae(et.$et)";
        }
        $pax = "{$booking->service_info["passengersCount"]["adults"]}ADT {$booking->service_info["passengersCount"]["children"]}CHD {$booking->service_info["passengersCount"]["infants"]}INF";
        $passengers = implode(', ', $passengers);
        return "$pax , $passengers";
    }

    protected function packageBooking($booking)
    {
        if (!empty($booking->seller_info['sellectedAvailability']['flights']['departure'] ?? null)) $allocationIds[] = $booking->seller_info['sellectedAvailability']['flights']['departure'];
        if (!empty($booking->seller_info['sellectedAvailability']['flights']['return'] ?? null)) $allocationIds[] = $booking->seller_info['sellectedAvailability']['flights']['return'];

        if (!empty($allocationIds)) {
            $allocations = FlightAllocation::whereIn('id', $allocationIds)->with('availability')->get();
            foreach ($allocations as $allocation) {
                $availability = $allocation->availability;
                $latestEticket = $availability->latest_eticket + 1;
                $allocationPassengers = $allocation->passengers_info ?? [];
                foreach ($allocationPassengers as $bookingId => $bookingPassengers) {
                    if ($bookingId == $booking->id) {
                        foreach ($bookingPassengers as $type => $typePassengers) {
                            foreach ($typePassengers as $passengerIndex => $passenger) {
                                $latestEticket++;
                                $allocationPassengers[$bookingId][$type][$passengerIndex]['ticket'] = $latestEticket;
                            }
                        }
                    }
                }
                $allocation->passengers_info = $allocationPassengers;
                $allocation->update();
                $availability->latest_eticket = $latestEticket;
                $availability->update();
                lugInfo('Ticket Issue For Package Job Done', ['booking' => $booking->toArray(), 'availability' => $availability->toArray(), 'allocation' => $allocation->toArray()]);
            }
        }
    }
}

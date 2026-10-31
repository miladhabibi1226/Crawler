<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics\Flight;

use Carbon\Carbon;

trait TripDetails
{
    protected $finalStatus = 'issued', $ticks = [];

    public function canBeDone()
    {
        return $this->authorizeRequest();
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/MystiFly/FlightTripDetails.json"));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                $this->object->status = 'incomplete';
                $this->object->update();
                return false;
            }
        }

        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            $this->object->status = 'incomplete';
            $this->object->update();
            return false;
        }
    }

    public function extractor()
    {
        $bookDataResult = $this->rawResult['decoded']['Data'] ?? '';
        if (empty($bookDataResult)) {
            lugError('empty book result ', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError('empty book result');
            $this->object->status = 'incomplete';
            $this->object->update();
            return false;
        }
        $this->items['bookStatus'] = $bookStatus = $bookDataResult['TripDetailsResult']['TravelItinerary']['BookingStatus'] ?? '';
        $this->items['ticketStatus'] = $ticketStatus = $bookDataResult['TripDetailsResult']['TravelItinerary']['TicketStatus'] ?? '';

        if ($bookStatus === 'NotBooked' || $ticketStatus === 'Pending') { //////////////??????????
            lugError('Status is pending or NotBooked', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError('Pending or NotBooked status ');
            $this->object->status = 'incomplete';
            $this->object->update();
            return false;
        }
        $this->objectStatus = 'issued';
        $segmentPnrs = $pnrs = [];
        foreach ($bookDataResult['TripDetailsResult']['TravelItinerary']['Itineraries'][0]['ItineraryInfo']['ReservationItems'] as  $itinerary) {
            if (empty($itinerary['AirlinePNR'])) {
                $this->objectStatus = 'waiting_issue';
                break;
            } else {
                $pnrs[] = $itinerary['AirlinePNR'];
            }
            $segmentPnrs[] = $itinerary['DepartureAirportLocationCode'] . '/' . $itinerary['ArrivalAirportLocationCode'];
        }
        $ticketInfo = $bookDataResult['TripDetailsResult']['TravelItinerary']['PassengerInfos'] ?? [];
        $this->items['bookingInfo'] = [
            'pnr' => array_unique($pnrs),
            'tickets' => $ticketInfo = $this->flightTicketInfo($this->getTickets($ticketInfo, $segmentPnrs)),
            'passengerCount' => count($bookDataResult['TripDetailsResult']['TravelItinerary']['PassengerInfos']) ?? null
        ];
        $this->items['sellerInfo'] = ['MystiFlyStatus' => "CONFIRMED"];
        $this->items['pnr'] = implode(',', $this->items['bookingInfo']['pnr']) . ':' . implode(',', $this->ticks);
        if (empty($this->ticks)) {
            $this->objectStatus = 'waiting_issue';
        }
        //ticket in process status
        if ($bookStatus === 'Booked' && $ticketStatus === 'TktInProcess') {
            $this->objectStatus = 'waiting_issue';
        }
        lugInfo('MystiFly TripDetails extractor Items', ['items' => $this->items]);
    }

    public function requestGenerator()
    {
        $this->call = [
            'fields' => '',
            'method' => 'GET',
            'url' =>  $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/v1.1/TripDetails/" . $this->object->seller_info['bookingResult']['UniqueID'],
            'apiMethod' => 'TripDetails'
        ];
        return $this->action();
    }

    protected function mapper()
    {
        $this->object->seller_info = array_merge($this->object->seller_info, $this->items['sellerInfo']);
        $this->object->booking_info = array_merge($booking_info ?? [], $this->items['bookingInfo'] ?? []);
        $this->object->booked_at = Carbon::now();
        $this->object->pnr = $this->items['pnr'];
        $this->object->async_action = $this->objectStatus == 'waiting_issue';
        $this->object->status = $this->objectStatus;
        $this->object->update();
        if ($this->object->status == 'issued') {
            $this->sellerTransaction();
            $this->itemIssued($this->items['bookingInfo']['pnr'] ?? $this->items['pnr']);
        }
    }

    protected function getTickets($ticketResult)
    {
        foreach ($ticketResult as $traveler) {
            $ticketNumber = [];
            try {
                $ticketNumberAll = array_column($traveler['ETickets'] ?? [], 'ETicketNumber') ?? [];
                $this->ticks = array_merge($this->ticks, $ticketNumberAll);
            } catch (\Throwable $th) {
                lugError('getTickets error: ' . $th->getMessage(), [$ticketResult]);
                $ticketNumberAll = [];
            }
            foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                foreach ($leg['segments'] as $segmentIndex => $segment) {
                    $ticketNumber[$legIndex][$segmentIndex] = $ticketNumberAll[$legIndex] ?? ($ticketNumberAll[0] ?? '');
                }
            }
            $passengerIndex = strtoupper(str_replace(' ', '', $traveler['Passenger']['PaxName']['PassengerLastName'] . $traveler['Passenger']['PaxName']['PassengerFirstName']));
            $passengers[$passengerIndex] = $ticketNumber ?? [];
        }
        return $passengers;
    }
}

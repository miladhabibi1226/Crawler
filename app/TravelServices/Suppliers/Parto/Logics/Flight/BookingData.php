<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Flight;

use Carbon\Carbon;

trait BookingData
{
    protected $finalStatus = 'issued', $ticks = [];

    public function canBeDone()
    {
        $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['decoded'] = json_decode(file_get_contents(storage_path('Samples/Parto/FlightBookingData.json')), true);
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            $this->object->status = 'incomplete';
            $this->object->update();
            return false;
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

    public function requestGenerator()
    {
        $this->call = [
            'fields' => json_encode(['UniqueId' => $this->object->seller_info['bookingResult']['uniqueId'], 'SessionId' => $this->latestSession]),
            'url' =>  $this->cleanUrlSlash($this->seller->api->configs['url']) . "/Rest/Air/AirBookingData",
            'apiMethod' => 'Air Booking Data'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $bookDataResult = $this->rawResult['decoded'];
        if ($this->object->seller_info['currentStep'] == 'book' && !in_array($bookDataResult['Status'], [20, 21])) {
            $this->checkForPriceChange($bookDataResult['TravelItinerary']['ItineraryInfo']['ItineraryPricing']); ///> book will fail in this if price changes, so we only check it on book step
        }
        lugInfo('Parto Booking Status: ' . $bookDataResult['Status']);
        switch ($bookDataResult['Status']) {
            case '10':
            case '11':
                $this->status10($bookDataResult);
                break;
            case '20':
                $this->status20($bookDataResult);
                break;
            case '21':
                $this->status21($bookDataResult);
                break;
            case '24':
            case '30':
                $this->status24($bookDataResult);
                break;
            default:
                $this->addError('Unsupported Status:' . $bookDataResult['Status']);
                break;
        }
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['bookingResult'] = array_merge($sellerInfo['bookingResult'], $this->items['sellerInfo']);
        $this->object->seller_info = $sellerInfo;

        $booking_info = $this->object->booking_info;
        $this->object->booking_info = array_merge($booking_info ?? [], $this->items['bookingInfo'] ?? []);
        $this->object->booked_at = Carbon::now();
        $this->object->pnr = $this->items['pnr'];
        $this->object->async_action = $this->objectStatus == 'waiting_issue';
        $this->object->status = $this->objectStatus;
        $this->object->update();
        if ($this->object->status == 'issued') {
            $this->itemIssued($this->items['bookingInfo']['pnr'] ?? $this->items['pnr']);
        }
    }

    public function status24($bookDataResult)
    {
        lugError("Parto status: Ticketed-Cancelled. payment unhandeled");
        foreach ($bookDataResult['TravelItinerary']['ItineraryInfo']['ReservationItems'] as  $item) {
            $pnrs[] = $item['AirlinePnr'] ?? null;
        }
        $this->items['bookingInfo'] = [
            'pnr' => array_unique($pnrs),
            'passengerCount' => count($bookDataResult['TravelItinerary']['ItineraryInfo']['CustomerInfoes']) ?? null
        ];
        $this->items['sellerInfo'] = ['partoStatus' => 24];
        $this->items['pnr'] = implode(',', $this->items['bookingInfo']['pnr']) . ':';
        $this->objectStatus = 'canceled';
        sendCancelledBySupplier($this->object);
        lugInfo('Parto BookingData extractor Items', ['items' => $this->items, 'objectStatus' => $this->objectStatus]);
    }

    public function status10($bookDataResult)
    {
        foreach ($bookDataResult['TravelItinerary']['ItineraryInfo']['ReservationItems'] as  $item) {
            $pnrs[] = $item['AirlinePnr'] ?? null;
        }
        $this->items['bookingInfo'] = [
            'pnr' => array_unique($pnrs),
            'passengerCount' => count($bookDataResult['TravelItinerary']['ItineraryInfo']['CustomerInfoes']) ?? null
        ];
        $this->items['sellerInfo'] = ['partoStatus' => 10];
        $this->items['pnr'] = implode(',', $this->items['bookingInfo']['pnr']) . ':';
        $this->objectStatus = 'validated';
        lugInfo('Parto BookingData extractor Items', ['items' => $this->items, 'objectStatus' => $this->objectStatus]);
    }

    public function status20($bookDataResult)
    {
        $this->objectStatus = 'issued';
        $segmentPnrs = [];
        foreach ($bookDataResult['TravelItinerary']['ItineraryInfo']['ReservationItems'] as  $item) {
            if (empty($item['AirlinePnr'])) {
                $this->objectStatus = 'waiting_issue';
                break;
            }
            $segmentPnrs[$item['AirlinePnr']][] = $item['DepartureAirportLocationCode'] . '/' . $item['ArrivalAirportLocationCode'];
        }
        $pnrs = array_keys($segmentPnrs);
        $ticketInfo = $bookDataResult['TravelItinerary']['ItineraryInfo']['CustomerInfoes'] ?? [];
        $this->items['bookingInfo'] = [
            'pnr' => array_unique($pnrs),
            'tickets' => $ticketInfo = $this->flightTicketInfo($this->getTickets($ticketInfo, $segmentPnrs)),
            'passengerCount' => count($bookDataResult['TravelItinerary']['ItineraryInfo']['CustomerInfoes']) ?? null
        ];
        $this->items['sellerInfo'] = ['partoStatus' => 20];
        $this->items['pnr'] = implode(',', $this->items['bookingInfo']['pnr']) . ':' . implode(',', $this->ticks);
        if (empty($this->ticks)) {
            $this->objectStatus = 'waiting_issue';
        }

        lugInfo('Parto BookingData extractor Items', ['items' => $this->items, 'ticks' => $this->ticks, 'objectStatus' => $this->objectStatus]);
    }

    public function status21($bookDataResult)
    {
        $this->objectStatus = 'issued';
        $result['passengerCount'] = count($bookDataResult['TravelItinerary']['ItineraryInfo']['CustomerInfoes']);
        $segmentPnrs = [];
        foreach ($bookDataResult['TravelItinerary']['ItineraryInfo']['ReservationItems'] as  $item) {
            if (empty($item['AirlinePnr'])) {
                break;
            }
            $segmentPnrs[$item['AirlinePnr']][] = $item['DepartureAirportLocationCode'] . '/' . $item['ArrivalAirportLocationCode'];
        }
        $pnrs = array_keys($segmentPnrs);
        $ticketInfo = $bookDataResult['TravelItinerary']['ItineraryInfo']['CustomerInfoes'];
        $this->items['bookingInfo'] = [
            'pnr' => array_unique($pnrs),
            'tickets' => $ticketInfo = $this->flightTicketInfo($this->getTickets($ticketInfo, $segmentPnrs)),
            'passengerCount' => count($bookDataResult['TravelItinerary']['ItineraryInfo']['CustomerInfoes']) ?? null
        ];
        $this->items['sellerInfo'] = ['partoStatus' => 21];
        $this->items['pnr'] = implode(',', $this->items['bookingInfo']['pnr']) . ':' . implode(',', array_unique($this->ticks));
        lugInfo('Parto BookingData extractor Items', ['items' => $this->items, 'ticks' => $this->ticks, 'objectStatus' => $this->objectStatus]);
    }

    protected function getTickets($ticketResult, $segmentPnrs)
    {
        $legSegments = $this->getLegSegments();
        foreach ($ticketResult as $traveler) {
            $ticketNumber = [];
            if (empty($traveler['ETicketNumbers'][0]['AirlinePnr'])) {
                $ticketNumberAll = array_column($traveler['ETicketNumbers'] ?? [], 'ETicketNumber') ?? [];
                $this->ticks = array_merge($this->ticks, $ticketNumberAll);
                foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                    foreach ($leg['segments'] as $segmentIndex => $segment) {
                        $ticketNumber[$legIndex][$segmentIndex] = $ticketNumberAll[$legIndex] ?? ($ticketNumberAll[0] ?? '');
                    }
                }
            } else {
                foreach ($traveler['ETicketNumbers'] as $ticketInfo) {
                    if (!empty($ticketInfo['ETicketNumber'])) {
                        $this->ticks[] = $ticketInfo['ETicketNumber'];
                    }
                    $segmentLocations = $segmentPnrs[$ticketInfo['AirlinePnr'] ?? '-'] ?? []; ///> value is something like [[IKA/IST],[IST/IKA]] or just one [[IKA/IST]]
                    if (!empty($segmentLocations)) {
                        foreach ($segmentLocations as $segmentLocation) {
                            $segment = $legSegments[$segmentLocation];
                            $ticketNumber[$segment['legIndex']][$segment['segmentIndex']] = $ticketInfo['ETicketNumber'] ?? '';
                        }
                    }
                }
            }
            $passengerIndex = strtoupper(str_replace(' ', '', $traveler['Customer']['PaxName']['PassengerLastName'] . $traveler['Customer']['PaxName']['PassengerFirstName']));
            $passengers[$passengerIndex] = $ticketNumber ?? [];
        }
        return $passengers;
    }

    protected function flightTicketInfo($passengers) ///> re-written for parto flights
    {
        $tickets = $fakeTickets = [];
        foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
            foreach ($leg['segments'] as $segmentIndex => $segment) {
                $fakeTickets[$legIndex][$segmentIndex] = 'Can not show ticket. Please contact admin';
            }
        }
        foreach ($this->object->passenger_info as $passengerType => $typePassengers) {
            foreach ($typePassengers as $passengerIndex => $passenger) {
                $tk = (array) ($passengers[strtoupper(str_replace(' ', '', $passenger['Last_name'] . $passenger['First_name']))] ?? []);
                $tickets[] = [
                    'passengerType' => $passengerType,
                    'passengerIndex' => $passengerIndex,
                    'ticket_number' => (empty($tk[0]) && ($this->rawResult['decoded']['Status'] ?? null) == 21) ? $fakeTickets : $tk
                ];
                if (empty($tk[0]) && ($this->rawResult['decoded']['Status'] ?? null) != 21) {
                    $this->objectStatus = 'waiting_issue';
                }

                if (empty($tk[0]) && ($this->rawResult['decoded']['Status'] ?? null) == 21) {
                    ///> بعضی وقتا پی ان آر موجود در بخش سگمنتها با پی ان آر موجود در بخش مسافران متفاوت است
                    ///> این باگ به پرتو گزارش شده ولی تا الان برطرف نشده
                    lugWarning('Parto has pnr problem again');
                }
            }
        }
        return $tickets;
    }
}

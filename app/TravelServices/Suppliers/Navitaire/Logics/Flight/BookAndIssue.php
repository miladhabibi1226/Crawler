<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics\Flight;

use App\TravelServices\Handler;
use App\TravelServices\Logics\Flight\Book as FlightBook;
use Carbon\Carbon;

trait BookAndIssue
{
    use FlightBook;
    protected $responseSample = 'Samples/Navitaire/FlightBookAndIssue.xml';

    public function performBeforeBookActions()
    {
        request()->merge(['action' => 'Payment', 'travelService' => 'flight']);
        $Payment = Handler::beforeBook($this->object->reference_id);
        return $Payment['status'] ?? false;
    }

    public function sessionHandling()
    {
        return $this->authorizeRequest($this->object->seller_info['bookingSession'] ?? '');
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth;
        lugInfo('BookAndIssue data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' =>  $this->cleanUrlSlash($auth['url']) . "/api/jz/v1/Booking/CommitBooking",
            'apiMethod' => 'BookAndIssue'
        ];
        return $this->action();
    }

    protected function getFields($depends)
    {
        $bookingReference = $this->object->getSerial();
        return [
            "receivedBy" => 'OTA',
            "restrictionOverride" => true,
            "notifyContacts" => false,
            "comments" => [
                [
                    "type" => "Default",
                    "text" => "Booking done from {$depends['companyName']} - reference id: $bookingReference",
                    "createdDate" => ""
                ]
            ]
        ];
    }

    public function extractor()
    {
        ///> Navitaire (jazeera) does not have tickets so we'll put pnr for tickets
        $BookResult = $this->rawResult['decoded']['data'] ?? [];
        $this->objectStatus = 'issued';
        $pnr = [$BookResult['bookingCommitv3']['recordLocator'] ?? null];
        if (empty($pnr)) {
            $this->addError('No pnr');
            lugWarning('No pnr', [$this->rawResult]);
            $this->failBook();
        }
        $this->items['bookingInfo'] = [
            'issued_at' =>  Carbon::now(),
            'tickets' => $this->flightTicketInfo($this->getTickets($BookResult['bookingCommitv3']['recordLocator'] ?? null)),
            'pnr' => array_unique($pnr ?? [])
        ];
        $this->items['pnr'] = implode('.', array_unique($pnr)) . ':' . implode('.', array_unique($pnr));
        lugInfo("Navitaire Flight BookAndIssue extractor Items", ['items' => $this->items, 'ticks' => []]);
    }

    protected function mapper()
    {
        $this->object->booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingInfo']);
        $this->object->pnr =  $this->items['pnr'];
        $this->object->booked_at = $this->items['bookingInfo']['issued_at'];
        $this->object->update();
    }

    protected function getTickets($pnr)
    {
        ///> Navitaire (jazeera) does not have tickets so we'll put pnr for tickets
        $passengers = [];
        foreach ($this->object->passenger_info as $passengerType => $typePassengers) {
            foreach ($typePassengers as $passenger) {
                foreach ($this->object->service_info['legs'] as $legIndex => $leg) {
                    foreach ($leg['segments'] as $segmentIndex => $segment) {
                        $passengers[strtoupper(str_replace(' ', '', $passenger['Last_name'] . $passenger['First_name']))][$legIndex][$segmentIndex] = $pnr;
                    }
                }
            }
        }
        return $passengers;
    }

    public function done()
    {
        request()->merge(['action' => 'BookingDetails', 'travelService' => 'flight']);
        return Handler::afterBook($this->object->reference_id);
    }

    protected function failed($errors = null)
    {
        $this->callSignOut($this->object->seller_info['bookingSession']);
        return ['status' => false];
    }
}

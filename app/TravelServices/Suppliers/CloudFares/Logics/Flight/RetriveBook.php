<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

use App\TravelServices\Handler;
use Carbon\Carbon;

trait RetriveBook
{
    protected $latestSession, $needsSchedule = false, $ticks = [];

    public function canBeDone()
    {
        return $this->findOrCreateSupplierSession();
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/CloudFares/FlightRetriveBook.json'));
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + ['bookingInfo' => $this->object->booking_info] + $this->object->seller_info;
        lugInfo('retriveBook Data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/b2b/v1/flight/get-book',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'retrive book'
        ];
        return $this->action();
    }

    protected function getFields($depends)
    {
        return [
            "pnr" => $depends['pnr'],
            "last_name" => $depends['lastName'],
        ];
    }

    protected function extractor()
    {
        $result = $this->rawResult['decoded']['data'] ?? [];
        //empty
        if (empty($result)) {
            lugError('empty book result ', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError(trans('technical'));
            return $this->contactAdminStatus();
        }
        $this->objectStatus = 'issued';
        $this->items['bookingInfo'] = [
            'pnr' => [$result['pnr']],
            'tickets' => $this->flightTicketInfo($this->getTickets($result ?? [])),
            'issued_at' =>  Carbon::now()
        ];
        if (empty($this->ticks[0]) || $result['status'] !== 'ticket') {
            $this->objectStatus = 'waiting_issue';
        }
        lugInfo('CloudFares RetriveBook extractor Items', ['items' => $this->items, 'ticks' => $this->ticks, 'objectStatus' => $this->objectStatus]);
    }

    protected function getTickets($result)
    {
        $passengers = [];
        foreach ($result['passengers'] as $traveler) {
            if (empty($traveler['ticketNo'])) {
                $this->objectStatus = 'waiting_issue';
            }
            foreach ($this->getServiceInfo()['legs'] as $legIndex => $leg) {
                foreach ($leg['segments'] as $segmentIndex => $segment) {
                    $this->ticks[] =   $ticketNumber[$legIndex][$segmentIndex] = $traveler['ticketNo'] ?? '';
                }
            }
            $passengers[strtoupper(str_replace(' ', '', $traveler['lastName'] . $traveler['firstName']))] = $ticketNumber;
        }
        return $passengers;
    }

    protected function mapper()
    {
        $booking_info = $this->object->booking_info;
        $this->object->booking_info = array_merge($booking_info ?? [], $this->items['bookingInfo'] ?? []);
        $this->object->async_action = $this->objectStatus == 'waiting_issue';
        $this->object->status = $this->objectStatus;
        $this->object->update();
    }
}

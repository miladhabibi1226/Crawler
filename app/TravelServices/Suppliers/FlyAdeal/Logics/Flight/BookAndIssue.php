<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics\Flight;

use App\TravelServices\Handler;
use App\TravelServices\Logics\Flight\Book as FlightBook;

trait BookAndIssue
{
    use FlightBook;
    protected $responseSample = 'Samples/FlyAdeal/FlightBookAndIssue.json';
    public function sessionHandling()
    {
        return $this->authorizeRequest($this->object->seller_info['bookingSession'] ?? '');
    }

    public function performBeforeBookActions()
    {
        if (!empty($this->queryParams['infants'])) {
            request()->merge(['action' => 'AddInfantDocuments', 'travelService' => 'flight']);
            $AddInfantDocuments = Handler::beforeBook($this->object->reference_id);
            if (!$AddInfantDocuments['status'] ?? false) {
                return false;
            }
        }
        request()->merge(['action' => 'Payment', 'travelService' => 'flight']);
        $Payment = Handler::beforeBook($this->object->reference_id);
        return $Payment['status'] ?? false;
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth;
        lugInfo('BookAndIssue data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' =>  $this->cleanUrlSlash($auth['url']) . "/api/nsk/v3/booking",
            'apiMethod' => 'BookAndIssue'
        ];
        return $this->action();
    }

    protected function getFields($depends)
    {

        return [
            "receivedBy" => "",
            "restrictionOverride" => false,
            "notifyContacts" => false,
            "comments" => [
                [
                    "type" => "Default",
                    "text" => "SampleBooking",
                    "sendToBookingSource" => true
                ]
            ]

        ];
    }

    public function extractor()
    {
        $Result = $this->rawResult['decoded']['data'] ?? [];
        if ($Result != null) {
            lugError('booking has problems', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('booking has problems');
        }
    }

    protected function mapper() {}

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

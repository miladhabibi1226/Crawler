<?php

namespace App\TravelServices\Suppliers\TopTravel\Logics\Flight;

use Carbon\Carbon;
use Illuminate\Support\Str;
use App\TravelServices\Handler;

trait BookAndIssue
{
    protected $otaTransactionId;

    public function canBeDone()
    {
        return $this->findOrCreateSupplierSession() && $this->performBeforeBookActions();
    }

    public function performBeforeBookActions()
    {
        request()->merge(['action' => 'Payment', 'travelService' => 'flight']);
        $Payment = Handler::beforeBook($this->object->reference_id);
        return $Payment['status'] ?? false;
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/TopTravel/FlightBook.json'));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->requestGeneratorFailed();
                return false;
            }
        }
        $this->decodeRawResult();
        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->seller_info + ['passengers' => $this->object->passenger_info];
        lugInfo('BookAndIssue data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' =>   $this->cleanUrlSlash($auth['url']) . '/satguruapiout/v1/book/flightbook',
            'headers' => [
                'Authorization:Bearer ' . $this->latestSession,
                'Content-Type: application/json'
            ],
            'apiMethod' => 'BookAndIssue'
        ];
        return $this->action();
    }

    protected function decodeRawResult()
    {
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
    }

    public function extractor()
    {
        $this->items['sellerInfo'] = [
            'otaTransactionId' => $this->otaTransactionId
        ];

        lugInfo('TopTravel Flight BookAndIssue extractor Items', ['items' => $this->items]);
    }

    protected function getFields($depends)
    {
        $data = [];

        foreach ($depends['passengers'] as $type => $typePassengers) {
            foreach ($typePassengers as $passenger) {
                $passengers[] = [
                    "title" => ['MR' => "0", 'MS' => "1", 'Mrs' => "2", "Miss" => "3"][$passenger['title']],
                    "firstName" => $passenger['First_name'],
                    "lastName" => $passenger['Last_name'],
                    "dob" => $passenger['Date of birth'],
                    "nationality" => $passenger['CountriesInfo']['Nationality']['iso'],
                    "type" => ['adults' => "A", 'children' => "C", 'infants' => "I"][$type],
                    "email" => $depends['passengers']['adults'][0]['Email'],
                    "phoneCode" => $depends['passengers']['adults'][0]['Phone']['countryPhoneCode'],
                    "contactNumber" => $depends['passengers']['adults'][0]['Phone']['phone']
                ];
            }
        }
        $prefix = "OTATrnID_EVA";
        $formattedDate = date("Ymd_His");
        $this->otaTransactionId = "{$prefix}_{$formattedDate}";
        $data =  [
            "agencyCode" =>  Str::before($depends['username'], ':'),
            "bookAndHold" => false,
            "userEmail" => Str::after($depends['username'], ':'),
            "otaTransactionId" => $this->otaTransactionId,
            "passengerList" => $passengers,
            "selectedFlightOptionKey" => $depends['selectedFlightOptionKey'],
            "bookingRequestBean" => [
                "productType" => 0,
                "modeOfPayment" => 2,
                "paymentGatewayId" => 0
            ]
        ];
        return $data;
    }

    protected function mapper()
    {
        $this->object->seller_info = array_merge($this->object->seller_info ?? [], $this->items['sellerInfo'] ?? []);
        $this->object->booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingInfo'] ?? []);
        $this->object->booked_at = Carbon::now();
        if ($this->bookingType == 'bookOnly') {
            $this->object->status = 'on_hold';
            $expireOn = $this->checkExpiryTime(Carbon::create($this->items['bookingInfo']['expires_at']));
            $this->object->expire_on = $expireOn;
        }
        if (!empty($this->items['bookingInfo']['pnr'][0])) {
            $this->object->pnr = implode(',', (array) $this->items['bookingInfo']['pnr']) . ':';
        }
        $this->object->status = $this->objectStatus;
        $this->object->update();
    }

    public function done()
    {
        request()->merge(['action' => 'bookingStatus', 'travelService' => 'flight']);
        return Handler::afterBook($this->object->reference_id);
    }
}

<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\New;
use App\TravelServices\Logics\Hotel\Book as HotelBook;

use Carbon\Carbon;

trait BookAndIssue
{
    use HotelBook;
    public function sessionHandling()
    {
        return true;
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth + $this->object->passenger_info + $this->object->service_info + $this->object->price_info + ['passengers' => $this->object->passenger_info];
        lugInfo('Book Data', [$depends]);
        $username = $auth['username'];
        $password = $auth['password'];
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $auth['url'] . '/Book',
            'headers' => [
                'Authorization: Basic ' . base64_encode("$username:$password")
            ],
            'apiMethod' => 'HotelBook'
        ];
        return $this->action();
    }

    protected function getFields($depends)
    {
        $roomIndexs = $depends['passengers'];
        $customerDetails = [];
        foreach ($roomIndexs as $roomIndex => $typePassengers) {
            $customerNames = [];
            foreach ($typePassengers as $type => $typePassenger) {
                foreach ($typePassenger as $passenger) {
                    $customerNames[] = [
                        "Title" => $passenger['title'],
                        "FirstName" => $passenger['First_name'],
                        "LastName" => $passenger['Last_name'],
                        "Type" => ucfirst($type)
                    ];
                }
            }
            $customerDetails[] = [
                "CustomerNames" => $customerNames
            ];
        }
        $timestamp = round(microtime(true) * 1000);
        $randomNumber = mt_rand(10000000, 99999999);
        $clientReferenceId = $timestamp . '-' . $randomNumber;
        $bookingReferenceId = $this->generateRandomString(10);

        $data = [
            "BookingCode" => $depends['supplierAvailabilityResponse']['bookingCode'],
            "CustomerDetails" => $customerDetails,
            "ClientReferenceId" => $clientReferenceId,
            "BookingReferenceId" => $bookingReferenceId,
            "TotalFare" => $depends['sellerPrice']['rawPrice']['totalFare'],
            "EmailId" => "apisupport@tboholidays.com",
            "PhoneNumber" => "918448780621",
            "BookingType" => "Voucher"
        ];

        return $data;
    }
    protected function generateRandomString($length)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $charactersLength = strlen($characters);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[rand(0, $charactersLength - 1)];
        }
        return $result;
    }


    public function extractor()
    {
        $result = $this->rawResult['decoded'] ?? [];
        if (empty($result)) {
            $this->addError('response is invalid');
            $this->addErrorToBook(trans('response is invalid'));
            lugError('empty result', [$this->rawResult]);
            return $this->failBook();
        }
        if (empty($result['ConfirmationNumber'])) {
            $this->addError('response is invalid');
            $this->addErrorToBook(trans('response is invalid'));
            return $this->failBook();
        }
        $this->items['bookingInfo'] = [
            'pnr' => $result['ConfirmationNumber'],
        ];
        $this->items['sellerInfo'] = [
            'bookingResult' => [
                'status' => $result['Status']['Description'] ??  '',
                'ClientReferenceId' => $result['ClientReferenceId'] ?? false
            ]
        ];
        lugInfo("TBO Hotel Book extractor Items", ['items' => $this->items]);
    }

    protected function mapper()
    {
        $this->object->seller_info = array_merge($this->object->seller_info ?? [], $this->items['sellerInfo'] ?? []);
        $this->object->refresh();
        $this->object->booking_info = array_merge($this->object->booking_info ?? [], $this->items['bookingInfo'] ?? []);
        $this->object->status = 'issued';
        $this->object->booked_at = Carbon::now();
        $this->object->pnr = $this->items['bookingInfo']['pnr'] . ':';
        $this->object->update();
        $this->itemIssued($this->items['bookingInfo']['pnr'] ?? []);
    }

    protected function done()
    {
        $this->sellerTransaction();
        return ['status' => true];
    }


    public function haveSupplierResponseError()
    {
        return $this->haveSupplierResponseErrorNew();
    }
}

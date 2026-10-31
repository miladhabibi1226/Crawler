<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Hotel;

use App\TravelServices\Logics\Hotel\Book as HotelBook;

trait Book
{
    use HotelBook;
    protected $responseSample = 'Samples/Parto/HotelBook.json';

    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        lugInfo('Book Data', [$this->queryParams, $this->latestSession, $this->object->passenger_info, $this->object->seller_info]);
        $this->call = [
            'fields' => json_encode($this->getFields()),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . '/Rest/Hotel/HotelBook',
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded'] ?? null;
        $this->items['bookingInfo'] = [
            'pnr' => $BookResult['UniqueId'],
            'expires_at' => $BookResult['PaymentDeadline'] ?? null,
            'remarks' => $BookResult['Remarks'] ?? [],
            'hotelPolicy' => $this->object->seller_info['checkRateResult']['HotelPolicy'] ?? []
        ];
        $this->items['sellerInfo'] = [
            'HotelBookResult' => [
                'Category' => $category = $BookResult['Category'] ?? null,
                'Status' => $status = $BookResult['Status'] ?? null,
            ]
        ];
        lugInfo("HotelBook status: $status, Category: $category");
        if (!in_array($status, [10, 11, 20]) || !in_array($category, [10, 20])) {
            lugError('Unexpected status for HotelBook', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError('Unexpected status for HotelBook');
        }
        lugInfo('Parto Hotel Book extractor Items', ['items' => $this->items]);
    }

    protected function getFields()
    {
        $passengers = $this->object->passenger_info;
        $data = [
            "FareSourceCode" => $this->object->seller_info['checkRateResult']['FareSourceCode'],
            "SessionId" => $this->latestSession,
            "ClientUniqueId" => $this->seller->api->configs['officeId'],
            "PhoneNumber" => $passengers[0]['adult'][0]['Phone']['countryPhoneCode'] . $passengers[0]['adult'][0]['Phone']['phone'],
            "Email" => $passengers[0]['adult'][0]['Email'],
        ];
        foreach ($passengers as $roomPassengers) {
            $Rooms = [];
            foreach ($roomPassengers as $type => $typePassenger) {
                foreach ($typePassenger as $passenger) {
                    $RoomPassenger = [
                        "FirstName" => $passenger['First_name'],
                        "LastName" => $passenger['Last_name'],
                        "PassengerType" => $type  == 'adult' ? 1 : '2',
                    ];
                    if ($type  != 'adult') {
                        $RoomPassenger['ChildAge'] = $passenger['Age'];
                    }
                    $Rooms['Passengers'][] = $RoomPassenger;
                }
            }
            $data['Rooms'][] = $Rooms;
        }
        return $data;
    }

    protected function failed($error = NULL)
    {
        $this->failBook();
        return ['status' => false, 'bookingStatus' => $this->object->status];
    }
}

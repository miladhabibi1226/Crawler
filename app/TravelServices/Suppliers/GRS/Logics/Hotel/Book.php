<?php

namespace App\TravelServices\Suppliers\GRS\Logics\Hotel;

use App\TravelServices\Logics\Hotel\Book as HotelBook;
use Carbon\Carbon;

trait Book
{
    use HotelBook;
    protected $responseSample = 'Samples/GRS/HotelBook.xml';

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs + ['guests' => $this->object->passenger_info, 'childrenRule' => $this->object->seller_info['children'], 'queryParams' => $this->queryParams, 'propertyId' => $this->object->seller_info['hotelCode'],  'selectedCombination' => $this->object->price_info['selectedCombination']];
        // lugInfo('Book data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($depends['url']) . '/v1/reserve',
            'headers' => [],
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    protected function getFields($depends)
    {
        $rooms = [];
        foreach ($depends['selectedCombination']['sellerPrice']['rawPrice'] as $key => $room) {
            $guests = $childAge = [];
            $adults = count($depends['guests'][$key]['adult']);
            foreach ($depends['guests'][$key] as $guestType => $guestGroup) {
                foreach ($guestGroup as $guest) {
                    $guests[] = [
                        "first_name" => $guest['First_name'],
                        "last_name" => $guest['Last_name'],
                        "national_code" => $guest['National Id'] ?? "",
                        "passport_number" => $guest['Passport Number'] ?? ""
                    ];
                    if ($guestType == 'child') {
                        $age = Carbon::create($guest['Date of birth'])->age;
                        if ($age > $depends['childrenRule']['max_child_age']) {
                            $adults++;
                        } else {
                            $childAge[] = $age;
                        }
                    }
                }
            }
            $rooms[] = [
                "room_type_id" => $room['room_type_id'],
                "rate_plan_id" => $room['rate_plan_id'],
                "count" => 1,
                "adult_count" => $adults,
                "children" => $childAge,
                "guest_first_name" => $guests[0]["first_name"],
                "guest_last_name" => $guests[0]["last_name"],
                "guest_national_code" => $guests[0]['national_code'],
                "guest_passport_number" => $guests[0]['passport_number'],
                "guests" => $guests
            ];
        }
        return [
            "property_id" => $depends['propertyId'],
            "check_in" => $depends['queryParams']['checkin'],
            "check_out" => $depends['queryParams']['checkout'],
            "booker_first_name" => $depends['guests'][0]['adult'][0]['First_name'],
            "booker_last_name" => $depends['guests'][0]['adult'][0]['Last_name'],
            "booker_phone" => '09010693590',
            "booker_email" => $depends['guests'][0]['adult'][0]['Email'],
            "rooms" => $rooms
        ];
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded']['value']['reserve'] ?? [];

        if (empty($BookResult)) {
            lugError('empty result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('empty result');
        }

        $this->checkPrice($BookResult);
        $this->checkState($BookResult);
        $GRSStatus = $this->checkStatus($BookResult);
        $this->items['bookingInfo'] = [
            'pnr' => $BookResult['confirmation_code'],
            'expires_at' => $GRSStatus == 'DEFINITE' ? null : $BookResult['expire_date'] ?? null
        ];
        $this->goingToCallIssue = $GRSStatus == 'BOOKING';

        lugInfo('GRS Hotel Book extractor Items', ['items' => $this->items, 'state' => $BookResult['state'], 'status' => $GRSStatus]);
    }

    protected function checkState($BookResult)
    {
        $state = $BookResult['state'];
        if (strtoupper($state) != 'ONLINE') {
            lugError('offline reserve', ['response' => $BookResult]);
            $this->addError('offline reserve');
        }
    }

    protected function checkStatus($BookResult)
    {
        $status = strtoupper($BookResult['status']);
        lugInfo('GRS status: ' . $status);
        if (!in_array($status, ['BOOKING', 'DEFINITE'])) {
            lugError('Unexpected status: ' . $status, ['response' => $BookResult]);
            $this->addError('Unexpected status: ' . $status);
        }
        return  $status;
    }

    protected function checkPrice($BookResult)
    {
        $resultPrice = $BookResult['total_sales_price'] ?? 0;
        $searchPrice = $this->object->price_info['bookingCalculations']['sellerPrice']['details']['bySellerCurrency']['payable'];
        if ($searchPrice != $resultPrice) {
            lugError('PRICE MSMATCH', ['bookingCalculations SellerPrice' => $this->object->price_info['bookingCalculations']['sellerPrice'], 'response' => $BookResult]);
            $this->addError('PRICE MSMATCH');
        }
    }

    protected function failed($error = NULL)
    {
        $this->failBook();
        return ['status' => false, 'bookingStatus' => $this->object->status];
    }
}

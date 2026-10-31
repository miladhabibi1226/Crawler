<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\New;

use App\TravelServices\Logics\BeforeBook;

trait AvailabilityAndPricing
{
    use BeforeBook;
    public function sessionHandling()
    {
        return true;
    }

    public function requestGenerator()
    {
        if ($this->objectIsBooking) {
            $bookingCode = $this->object->price_info['selectedCombination']['sellerPrice']['rawPrice']['bookingcode'];
            $combination = $this->object->price_info['selectedCombination'];
        } else {
            $matchedCombination = null;
            $selectedRoomIndexes = request()->selectedCombination; 
            $combinationPrices = request()->combinationPrice;
            $bookingCode = null;
            foreach ($combinationPrices as $combination) {
                $roomIndexes = $combination['roomIndexes'] ?? [];   
                if (empty(array_diff($selectedRoomIndexes, $roomIndexes))) {
                    $bookingCode = $combination['sellerPrice']['rawPrice']['bookingcode'] ?? null;
                    break; 
                }
            }
        }
        $auth = $this->seller->api->configs;
        $username = $auth['username'];
        $password = $auth['password'];
        $depends = $this->object->seller_info + $auth + ['combination' => $combination['roomIndexes']];
        $this->call = [
            'fields' => json_encode(["BookingCode" => $bookingCode, 'PaymentMode' => 'Limit']),
            'url' => $auth['url'] . '/PreBook',
            'headers' => [
                'Authorization: Basic ' . base64_encode("$username:$password")
            ],
            'apiMethod' => 'Availablity And Pricing'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $result = $this->rawResult['decoded']['HotelResult'][0]['Rooms'][0];
        //empty result
        if (empty($result)) {
            $this->addError('response is invalid');
            $this->addErrorToBook(trans('response is invalid'));
            lugError('empty result', [$result]);
            return $this->failBook();
        }
        //check price
        $oldPrice = $this->object->price_info['pricing']['payable'];
        if (round($result['TotalFare'], 5) !== round($oldPrice, 5)) {
            lugError('Price changed', ['old price' =>  $this->object->price_info, 'new price ' => $result]);
            $this->addError('Price has changed');
            $this->addErrorToBook(trans('messages.priceChanged'));
            return $this->failBook();
        }
        $bookingCode = $result['BookingCode'] ?? '';
        if (empty($bookingCode)) {
            $this->addError('response is invalid');
            $this->addErrorToBook(trans('response is invalid'));
            return $this->failBook();
        }
        $this->items = [
            'bookingCode' => $bookingCode,
            'HotelPolicy' => $result['CancelPolicies'] ?? []
        ];
        lugInfo('TBO AvailabilityAndPricing Extractor items', [$this->items]);
    }

    protected function mapper()
    {
        $this->object->seller_info = $this->object->seller_info + ['supplierAvailabilityResponse' => $this->items];
        $this->object->update();
    }
    public function haveSupplierResponseError()
    {
        return $this->haveSupplierResponseErrorNew();
    }
}

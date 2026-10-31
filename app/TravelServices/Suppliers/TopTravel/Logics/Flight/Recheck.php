<?php

namespace App\TravelServices\Suppliers\TopTravel\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;
use Illuminate\Support\Str;

trait Recheck
{
    use BeforeBook;

    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs + ['passengers' => $this->object->passenger_info] + $this->object->seller_info;
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/satguruapiout/v1/book/fareRecheck",
            'headers' => [
                'Authorization:Bearer ' . $this->latestSession,
                'Content-Type: application/json'
            ],
            'apiMethod' => 'Recheck'
        ];
        return $this->action();
    }

    protected function getFields($depends)
    {
        $passengers = [];
        foreach ($depends['passengers'] as $type => $typePassengers) {
            foreach ($typePassengers as $passenger) {
                $passengers[] = [
                    "title" =>  $passenger['title'],
                    "firstName" => $passenger['First_name'],
                    "lastName" => $passenger['Last_name'],
                    "dob" => $passenger['Date of birth'],
                    "nationality" => $passenger['CountriesInfo']['Nationality']['iso'],
                    "type" => ['adults' => "ADT", 'children' => "CHD", 'infants' => "INF"][$type],
                    "email" => $depends['passengers']['adults'][0]['Email'],
                    "phoneCode" => $depends['passengers']['adults'][0]['Phone']['countryPhoneCode'],
                    "contactNumber" => $depends['passengers']['adults'][0]['Phone']['phone']
                ];
            }
        }
        $data = [
            "agencyCode" =>  Str::before($depends['username'], ':'),
            "selectedFlightOptionKey" => $depends['selectedFlightOptionKey'],
            "passengerList" => $passengers
        ];
        return $data;
    }

    public function extractor()
    {
        if ($this->queryParams['tripType'] === 'oneWay') {
            $result = $this->rawResult['decoded']['onwardFlightOption'];
            $totalFare = $result['totalFare'];
        } else {
            $result = $this->rawResult['decoded']['roundTripFlightOption'];
            $totalFare = $result['onwardFlightOption']['totalFare'] +  $result['returnFlightOption']['totalFare'];
        }
        if (empty($totalFare)) {
            lugError('no priceInfo', [$this->rawResult]);
            $this->addError('no PricingInfo');
            $this->addErrorToBook(trans('messages.unavailable'));
            $this->failBook();
            return false;
        }
        $this->checkForPriceChange($totalFare);
    }

    protected function checkForPriceChange($totalFare)
    {
        ///> currently we will fail the booking if the price has changed
        $oldPrice = $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'];
        if (round($totalFare, 4) !== round($oldPrice, 4)) {
            lugError('Price Change', ['Old Price' => $oldPrice, 'New Price' => $totalFare]);
            $this->addError("Price has changed");
            if ($this->objectIsBooking) {
                $this->failBook();
                $this->addErrorToBook(trans('messages.priceChanged'));
            }
        }
    }
}

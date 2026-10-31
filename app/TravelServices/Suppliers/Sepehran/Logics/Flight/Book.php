<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics\Flight;

use Carbon\Carbon;
use App\TravelServices\Handler;
use App\TravelServices\Logics\Flight\Book as FlightBook;


trait Book
{
    use FlightBook;

    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->seller_info + ['passengers' => $this->object->passenger_info];
        lugInfo('Book data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' =>   $this->cleanUrlSlash($auth['url']) . '/api/v2/flight/Book',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded'];
        if (empty($BookResult)) {
            lugError('empty result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('empty result');
        }

        $this->items['bookingInfo'] = [
            'expires_at' => ''
        ];
        $this->items['sellerInfo'] = [
            'bookingResult' => ['refrence_id' => $BookResult['refrence_id'] ?? ''],
            'supplierReference' => $BookResult['refrence_id'] ?? ''
        ];

        lugInfo('Sepeharn Book extractor Items', ['items' => $this->items]);
    }

    public function done()
    {
        request()->merge(['action' => 'confirm', 'travelService' => 'flight']);
        return Handler::afterBook($this->object->reference_id);
    }

    protected function getFields($depends)
    {
        $sellerInfo = $this->object->seller_info;
        $fields = [];
        $fields = [
            "outbound" => [
                "ref_number" => $this->object->seller_info['refNumbers']['outbound'],
                "departure" => $this->object->service_info['legs'][0]['segments'][0]['departure']['airport']['abb'],
                "arrival" => $this->object->service_info['legs'][0]['segments'][0]['arrival']['airport']['abb'],
                "date" =>  $this->object->service_info['legs'][0]['segments'][0]['departure']['raw_time']
            ],
            "receiver" => [
                "mobile" => $this->seller->api->configs['passengerContactPhone']
            ],
            "price" => [
                "currency_code" => $this->seller->currency->abb,
                "total" => $this->object->price_info['bookingCalculations']['sellerPrice']['details']['bySellerCurrency']['payable']
            ],

        ];

        $infantNumber = (int)$this->object->service_info['query_params']['infants'];


        foreach ($depends['passengers'] as $type => $typePassengers) {
            foreach ($typePassengers as $passenger) {
                $accompanied_by_infant = false;
                if ($type === 'adults' && $infantNumber > 0) {
                    $accompanied_by_infant = true;
                    $infantNumber--;
                }

                $passengers = [
                    "type" => ['adults' => "ADT", 'children' => "CHD", 'infants' => "INF"][$type],
                    "gender" => ['MALE' => "M", 'FEMALE' => "F"][strtoupper($passenger['Gender'])],
                    "accompanied_by_infant" => $accompanied_by_infant,
                    "prefix" => $passenger['title'],
                    "given_name" => $passenger['First_name'],
                    "surname" => $passenger['Last_name'],
                    "birthdate" => $passenger['Date of birth'],
                    "telephone" =>  $this->seller->api->configs['passengerContactPhone'],
                    "email" =>  $this->seller->api->configs['passengerContactEmail'],
                    "nationality" => $passenger['CountriesInfo']['Nationality']['iso'],
                ];
                if ($sellerInfo['isDomesticFlight'] && $passenger['CountriesInfo']['Nationality']['abb'] == 'IR') {
                    $passengers['national_id'] = $passenger['National Id'] ?? '';
                } else {
                    $passengers['passport'] = [
                        "id" => $passenger['Passport Number'] ?? '',
                        "expire_date" => $passenger['Passport Expiry Date'] ?? '',
                        "doc_issue_country" => $passenger['CountriesInfo']['Nationality']['iso'] ?? ''
                    ];
                }

                $fields['passengers'][] = $passengers;
            }
        }

        if (!empty($this->object->seller_info['refNumbers']['return'])) {
            $fields['return'] = [
                "ref_number" => $this->object->seller_info['refNumbers']['return'],
                "departure" => $this->object->service_info['legs'][1]['segments'][0]['departure']['airport']['abb'],
                "arrival" => $this->object->service_info['legs'][1]['segments'][0]['arrival']['airport']['abb'],
                "date" =>   $this->object->service_info['legs'][1]['segments'][0]['departure']['raw_time']
            ];
        }
        return $fields;
    }
}

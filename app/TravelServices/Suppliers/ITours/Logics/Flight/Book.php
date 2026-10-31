<?php

namespace App\TravelServices\Suppliers\ITours\Logics\Flight;

use App\TravelServices\Logics\Flight\Book as FlightBook;

trait Book
{
    use FlightBook;
    protected $responseSample = 'Samples/ITours/FlightBook.xml';

    public function sessionHandling()
    {
        return $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + ['passengers' => $this->object->passenger_info,  'sellerInfo' => $this->object->seller_info];
        $fields = [
            "key" => $depends['sellerInfo']['bookingRequestData']['validateKey'],
            "captchaCode" => null,
            "hasExtraBaggage" => false,
            "passengers" => $this->getPassengers($depends['passengers'])
        ];
        lugInfo('Book data', $depends);
        $this->call = [
            'fields' => json_encode($fields),
            'url' => $this->cleanUrlSlash($auth['url']) . '/api/Partners/Flight/V2/Reserve',
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded']['result'] ?? $this->callException('empty result');

        $this->checkForPriceChange($BookResult['flightFare']['totalFare']);

        $this->chcekReserveStatus($BookResult['reserveStatus']);

        $this->items['bookingInfo'] = [
            'pnr' => (array) ($BookResult['pnrCode'] ?? null),
            'expires_at' =>  $BookResult['expirationTime']
        ];
        $this->items['sellerInfo'] = [
            'bookingRequestData' => ['reserveId' => $BookResult['reserveId']],
            'ReserveResponse' => [
                'reserveId' => $BookResult['reserveId'],
                'reserveStatus' => $BookResult['reserveStatus'],
                'expires_at' => $BookResult['expirationTime'],
            ],
            'supplierReference' => $BookResult['reserveId'],
        ];

        lugInfo("ITours Flight $this->action extractor Items", ['items' => $this->items]);
    }

    protected function getPassengers($passengers)
    {
        $array = [];
        $types = ['adults' => 'ADT', 'children' => 'CHD', 'infants' => 'INF'];
        foreach ($passengers as $type => $typeGroup) {
            foreach ($typeGroup as $index => $passenger) {
                $array[] = [
                    "code" => $types[$type] ?? null,
                    "firstName" => $passenger['First_name'],
                    "lastName" => $passenger['Last_name'],
                    "domesticFirstName" => $passenger['Domestic first name'] ?? null,
                    "domesticLastName" => $passenger['Domestic last name'] ?? null,
                    "passportNumber" => $passenger['Passport Number'] ?? null,
                    "nationalId" => $passenger['National Id'] ?? null,
                    "birthDate" => $passenger['Date of birth'] ?? null,
                    "gender" => strtoupper($passenger['Gender'] ?? '') == 'MALE',
                    "passportExpireDate" => $passenger['Passport Expiry Date'] ?? null,
                    "nationality" => $passenger['CountriesInfo']['Nationality']['abb'] ?? null
                ];
            }
        }
        return $array;
    }

    protected function failed($error = null)
    {
        if (empty($this->object->seller_info['supplierReference'])) {
            $this->object->seller_info = array_merge($this->object->seller_info ?? [], ['supplierReference' => $this->rawResult['decoded']['result']['reserveId'] ?? '']);
            $this->object->update();
        }
        return ['status' => false];
    }
}

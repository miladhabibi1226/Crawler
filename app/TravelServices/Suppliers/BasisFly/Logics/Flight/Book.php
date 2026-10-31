<?php

namespace App\TravelServices\Suppliers\BasisFly\Logics\Flight;


use App\TravelServices\Logics\Flight\Book as FlightBook;
use Carbon\Carbon;
use App\Models\SupplierSession;

trait Book
{
    use FlightBook;

    public function sessionHandling()
    {
        $this->checkSession($this->object->seller_info['latestSession']);
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->seller_info + ['passengers' => $this->object->passenger_info];
        lugInfo('Book data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' =>   $this->cleanUrlSlash($auth['url']) . '/api-test/Flight_v2/Reserve',
            'headers' => [],
            'timeout' => 15,
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
        if ($BookResult['PriceChange'] != null) {
            $this->failBook();
            return $this->addError('price changing');
        }
        $this->checkForPriceChange($BookResult);

        $this->items['bookingInfo'] = [
            'expires_at' => ''
        ];
        $this->items['sellerInfo'] = [
            'bookingResult' => ['BasisFlyBookId' => $BookResult['AirReservation']['BasisFlyBookId'] ?? ''],
            'supplierReference' => $BookResult['AirReservation']['BasisFlyBookId'] ?? ''
        ];

        lugInfo('BasisFly Book extractor Items', ['items' => $this->items]);
    }

    protected function getFields($depends)
    {
        $flightGroup = $this->prepareFlightGroup();
        $sellerInfo = $this->object->seller_info;
        $fields = [];
        $fields = [
            "ApiKey" => $depends['apiKey'],
            "SessionId" => $this->object->seller_info['latestSession'],
            "FlightId" => $sellerInfo['FlightId'],
            "FlightGroup" => $flightGroup
        ];
        foreach ($depends['passengers'] as $type => $typePassengers) {
            foreach ($typePassengers as $passenger) {
                $travelers = [
                    "Type" => ['adults' => "ADT", 'children' => "CHD", 'infants' => "INF"][$type],
                    "FirstName" => $passenger['First_name'],
                    "LastName" => $passenger['Last_name'],
                    "Gender" => ['MALE' => "MR", 'FEMALE' => "MS"][strtoupper($passenger['Gender'])],
                    "DateOfBirth" =>  $passenger['Date of birth'],
                    "PlaceOfBirth" => $passenger['CountriesInfo']['Nationality']['iso']
                ];
                if ($sellerInfo['isDomesticFlight'] && $passenger['CountriesInfo']['Nationality']['abb'] == 'IR') {
                    $travelers['NationalCode'] = $passenger['National Id'] ?? '';
                } else {
                    $travelers['PassportCode'] = $passenger['Passport Number'] ?? '';
                    $travelers['PassportExpiration'] = $passenger['Passport Expiry Date'] ?? '';
                }

                $fields['Travelers'][] = $travelers;
            }
        }
        return $fields;
    }

    protected function checkForPriceChange($BookResult)
    {
        lugInfo('Price Change', ['search' => $this->object->price_info['sellerPrice']['rawPrice'], 'result' => $BookResult]);
        ///> currently we will fail the booking if the price has changed
        $searchTotalPrice = $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'];
        if (!$this->fromFile && $BookResult['AirReservation']['PriceInfo']['Total'] != $searchTotalPrice) {
            lugError('Price Change', ['search' => $this->object->price_info['sellerPrice']['rawPrice'], 'result' => $BookResult]);
            $this->addError("Price has changed");
            if ($this->objectIsBooking) {
                $this->failBook();
                $this->addErrorToBook(trans('messages.priceChanged'));
            }
        }
    }
}

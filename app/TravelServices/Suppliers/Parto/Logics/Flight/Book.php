<?php

namespace App\TravelServices\Suppliers\Parto\Logics\Flight;

use App\TravelServices\Handler;
use App\TravelServices\Logics\Flight\Book as FlightBook;

trait Book
{
    use FlightBook {
        FlightBook::needsUrgentInvestigation as commonNeedsUrgentInvestigation;
    }
    protected $responseSample = 'Samples/Parto/FlightBook.json';

    public function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        lugInfo('Book Data', ['seller configs' => $this->seller->api->configs, 'sellerInfo' => $this->object->seller_info, 'latestSession' => $this->latestSession, 'passengers' => $this->object->passenger_info]);
        $this->call = [
            'fields' => json_encode($this->getFields()),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/Rest/Air/AirBook",
            'apiMethod' => 'Air Book'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded'];
        if ($BookResult['PriceChange']) {
            lugError('PriceChange in book request', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            $this->addError('PriceChange');
        }

        $this->items['bookingInfo'] = [
            'expires_at' => $BookResult['TktTimeLimit'] ?? ''
        ];
        $this->items['sellerInfo'] = [
            'bookingResult' => ['uniqueId' => $BookResult['UniqueId'] ?? ''],
            'currentStep' => 'book',
            'supplierReference' => $BookResult['UniqueId'] ?? ''
        ];

        lugInfo('Parto Book extractor Items', ['items' => $this->items]);
    }

    protected function getFields()
    {
        $sellerInfo = $this->object->seller_info;
        $passengers = $this->object->passenger_info;
        $data = [
            "FareSourceCode" => $sellerInfo['FareSourceCode'],
            "ClientUniqueId" => $this->object->reference_id,
            "SessionId" => $this->latestSession,
            "TravelerInfo" => [
                "PhoneNumber" => $this->seller->api->configs['passengerContactPhone'],
                "Email" => $this->seller->api->configs['passengerContactEmail'],
            ]
        ];
        foreach ($passengers as $type => $typePassengers) {
            foreach ($typePassengers as $passenger) {
                $AirTraveler = [
                    "DateOfBirth" => $passenger['Date of birth'],
                    "Gender" => ['MALE' => 0, 'FEMALE' => 1][strtoupper($passenger['Gender'])],
                    "PassengerType" => ['adults' => 1, 'children' => 2, 'infants' => 3][$type],
                    "Nationality" => $passenger['CountriesInfo']['Nationality']['abb'],
                    "PassengerName" => [
                        "PassengerFirstName" => $passenger['First_name'],
                        "PassengerLastName" => $passenger['Last_name'],
                        "PassengerTitle" => $this->getPassengerTitle($type, $passenger['title']),
                    ]
                ];

                if ($sellerInfo['isDomesticFlight'] && $passenger['CountriesInfo']['Nationality']['abb'] == 'IR') {
                    $AirTraveler['NationalId'] = $passenger['National Id'] ?? '';
                } else {
                    $AirTraveler['Passport'] = [
                        "ExpiryDate" => $passenger['Passport Expiry Date'] ?? '',
                        "PassportNumber" => $passenger['Passport Number'] ?? '',
                        "Country" => $passenger['CountriesInfo']['Nationality']['abb'] ?? ''
                    ];
                }
                $data['TravelerInfo']['AirTravelers'][] = $AirTraveler;
            }
        }
        return $data;
    }

    protected function getPassengerTitle($type, $title)
    {
        if ($type == 'adults') {
            return ['MR' => 0, 'MS' => 2][strtoupper($title)];
        }
        return ['MISS' => 3, 'MSTR' => 4][strtoupper($title)];
    }

    protected function needsUrgentInvestigation()
    {
        if ($this->commonNeedsUrgentInvestigation()) return true;
        $errorMessage = $this->getSupplierErrorMessage();
        foreach ($this->urgentInvestigationEerrorPatterns() as $errorPattern) {
            $errorPattern = str_replace('/', '-', $errorPattern);
            if (preg_match("/$errorPattern/", $errorMessage)) {
                return true;
            }
        }
        return false;
    }

    public function done()
    {
        request()->merge(['action' => 'bookingData', 'travelService' => 'flight']);
        return Handler::afterBook($this->object->reference_id);
    }

    protected function failed($error = null)
    {
        if (empty($this->object->seller_info['supplierReference'])) {
            $this->object->seller_info = array_merge($this->object->seller_info ?? [], ['supplierReference' => $this->rawResult['decoded']['UniqueId'] ?? '']);
            $this->object->update();
        }
        return ['status' => false];
    }
}

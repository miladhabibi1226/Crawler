<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics\Flight;

use App\TravelServices\Handler;
use App\TravelServices\Logics\Flight\Book as FlightBook;

trait Book
{
    use FlightBook;
    protected $responseSample = 'Samples/MystiFly/FlightBook.json';

    public function sessionHandling()
    {
        return $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        luginfo("MystiFly Book Data", ['sellerInfo' => $this->object->seller_info, 'passengers' => $this->object->passenger_info]);
        $this->call = [
            'fields' => json_encode($this->getFields()),
            'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/v1/Book/Flight",
            'timeout' => 120,
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    protected function requestGeneratorFailed()
    {
        ///> supplier has told us it is mandatory to put a 120 sec timeout. if we reach the timeout we'll be here
        //> right now we can't make sure if the problem is timeout or not so we'll have to put all of it to contact_admin status (if there is clear response from the supplier we won't be here)
        $this->addError("Supplier connection error");
        $this->contactAdminStatus();
    }

    public function extractor()
    {
        $bookResult = $this->rawResult['decoded']['Data'] ?? [];
        if (empty($bookResult) || ($bookResult['Success'] ?? false) != true) {
            lugError('empty result error ', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            $this->addError('empty result error');
        }
        //based on MystiFly documentation if book status is equal PENDING and there is no UniqueID in book result we should fail book
        if ($bookResult['Status'] === 'PENDING' && empty($bookResult['UniqueID'])) {
            lugError('Status is pending and UniqueID is empty in book request', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            $this->addError('Pending status-empty UniqueID ');
        }
        $this->items['MystiFlyStatus'] = $bookResult['Status'];
        $this->items['bookingInfo'] = [
            'expires_at' => $bookResult['TktTimeLimit'] ?? ''
        ];
        $this->items['sellerInfo'] = [
            'bookingResult' => ['UniqueID' => $bookResult['UniqueID'] ?? ''],
            'supplierReference' => $bookResult['UniqueID'] ?? ''
        ];
        lugInfo('MystiFly Book extractor Items', ['items' => $this->items]);
    }

    protected function getFields()
    {
        $sellerInfo = $this->object->seller_info;
        $passengers = $this->object->passenger_info;
        $requiredPassengerInfo = $sellerInfo['validateResult']['RequiredFieldsToBook'];
        $data = [
            "Target" => "Test",
            "FareSourceCode" => $sellerInfo['validateResult']['FareSourceCode'],
            "TravelerInfo" => [
                "PhoneNumber" =>  substr($passengers['adults'][0]['Phone']['phone'], 3),
                "CountryCode" => trim($passengers['adults'][0]["Phone"]["countryPhoneCode"], '+'),
                "AreaCode" => substr($passengers['adults'][0]['Phone']['phone'], 0, 3),
                "Email" => $passengers['adults'][0]['Email'],
                "PostCode" => $passengers['adults'][0]['Post Code'] ?? ''
            ]
        ];
        foreach ($passengers as $type => $typePassengers) {
            foreach ($typePassengers as $passenger) {
                $AirTraveler =   [
                    "PassengerType" => ['adults' => "ADT", 'children' => "CHD", 'infants' => "INF"][$type],
                    "Gender" => ['MALE' => "M", 'FEMALE' => "F"][strtoupper($passenger['Gender'])],
                    "PassengerName" => [
                        "PassengerTitle" => $this->getPassengerTitle($type, $passenger['title']),
                        "PassengerFirstName" => $passenger['First_name'],
                        "PassengerLastName" => $passenger['Last_name']
                    ]
                ];
                if (in_array('Passport', $requiredPassengerInfo) || in_array('Passport No', $requiredPassengerInfo)) {
                    $AirTraveler['Passport'] = [
                        "PassportNumber" => $passenger['Passport Number'] ?? '',
                        "ExpiryDate" => $passenger['Passport Expiry Date'] ?? '',
                        "Country" => $passenger['CountriesInfo']['Passport Issuing Country']['abb'] ?? ''
                    ];
                }
                if (in_array('DOB', $requiredPassengerInfo)) {
                    $AirTraveler['DateOfBirth'] = $passenger['Date of birth'];
                }
                if (in_array('NationalID', $requiredPassengerInfo)) {
                    $AirTraveler['NationalID'] = $passenger['National Id'];
                }
                if (in_array('PassengerNationality', $requiredPassengerInfo)) {
                    $AirTraveler['PassengerNationality'] = $passenger['CountriesInfo']['Nationality']['abb'];
                }
                $data['TravelerInfo']['AirTravelers'][] = $AirTraveler;
            }
        }
        return $data;
    }

    protected function getPassengerTitle($type, $title)
    {
        if ($type == 'adults') {
            return ['MR' => "Mr", 'MS' => "Mrs"][strtoupper($title)];
        }
        return ['MISS' => "Miss", 'MSTR' => "Mstr"][strtoupper($title)];
    }

    public function done()
    {
        request()->merge(['action' => 'tripDetails', 'travelService' => 'flight']);
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

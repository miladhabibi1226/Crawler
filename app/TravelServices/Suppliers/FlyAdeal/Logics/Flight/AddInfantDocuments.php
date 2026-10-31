<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;

trait AddInfantDocuments
{
    use BeforeBook;
    protected $responseSample = 'Samples/Navitaire/AddPassengers.json';
    public function sessionHandling()
    {
        return $this->authorizeRequest($this->object->seller_info['bookingSession'] ?? '');
    }

    protected function decodeRawResult() ///> json suppliers use this, XML suppliers rewrite this
    {
        foreach ($this->rawResult as $key => $rawResult) {
            $this->rawResult[$key]['decoded'] = json_decode($rawResult['body'], true);
        }
    }

    public function requestGenerator()
    {
        $this->multi = true;
        $infants = $this->object->passenger_info['infants'] ?? [];
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth;
        foreach ($this->object->seller_info['passengersKeys'] as $passengerType) {
            foreach ($passengerType as $passengerKey) {
                if (!empty($infants)) {
                    $infant = array_shift($infants);
                    $this->prepareCalls($passengerKey, $depends + ['infant' => $infant, 'docType' => 'N']);
                    if (!$depends['isDomesticFlight']) {
                        $this->prepareCalls($passengerKey, $depends + ['infant' => $infant, 'docType' => 'P']);
                    } elseif ($infant['CountriesInfo']['Nationality']['abb'] == 'SA') {
                        $this->prepareCalls($passengerKey, $depends + ['infant' => $infant, 'docType' => 'I']);
                    }
                }
            }
        }
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth;
        return $this->action();
    }

    protected function prepareCalls($passengerKey, $depends)
    {
        lugInfo('AddInfantDocuments data', [$depends]);
        $this->call[] = [
            'fields' => json_encode($this->getFields($depends)),
            'timeout' => 20,
            'url' =>  $this->cleanUrlSlash($depends['url']) . "/api/nsk/v2/booking/passengers/$passengerKey/infant/documents",
            'apiMethod' => 'AddInfantDocuments'
        ];
    }

    public function extractor()
    {
        foreach ($this->rawResult as $key => $rawResult) {
            $passengerTravelDocumentKey = $rawResult['decoded']['data']['passengerTravelDocumentKey'] ?? null;
            if (empty($passengerTravelDocumentKey)) {
                $this->addError('could not add infant docs');
            }
            $this->items[] = $passengerTravelDocumentKey;
        }
        lugInfo('Navitaire Flight AddPassengers extractor Items', ['items' => $this->items]);
    }

    protected function getFields($depends)
    {

        $fields = [
            "birthCountry" => $depends['infant']['CountriesInfo']['Nationality']['abb'],
            "name" => [
                'first' => $depends['infant']['First_name'],
                'last' => $depends['infant']['Last_name'],
                'title' => $depends['infant']['title'],
                'suffix' => ''
            ],
            "nationality" => $depends['infant']['CountriesInfo']['Nationality']['abb'],
            "gender" => strtoupper($depends['infant']['Gender']) == 'MALE' ? 1 : 2,
            "dateOfBirth" => $depends['infant']['Date of birth']
        ];

        if ($depends['docType'] == 'N') {
            $fields = $fields + [
                "ISSUEDBYCODE" => $depends['infant']['CountriesInfo']['Nationality']['abb'],
                "expirationDate" => $depends['infant']['NationalId Expiry Date'],
                "number" => $depends['infant']['National Id'],
                "documentTypeCode" => "N",
            ];
        } elseif ($depends['docType'] == 'P') {
            $fields = $fields + [
                "ISSUEDBYCODE" => $depends['infant']['CountriesInfo']['Nationality']['abb'],
                "expirationDate" => $depends['infant']['Passport Expiry Date'],
                "number" => $depends['infant']['Passport Number'],
                "documentTypeCode" => "P",
            ];
        } elseif ($depends['docType'] == 'I') {
            $fields = $fields + [
                "ISSUEDBYCODE" => $depends['infant']['CountriesInfo']['Nationality']['abb'],
                "expirationDate" => $depends['infant']['Iqama Expiry Date'],
                "number" => $depends['infant']['Iqama'],
                "documentTypeCode" => "I"
            ];
        }

        return $fields;
    }

    protected function handleSignOut()
    {
        $this->callSignOut($this->object->seller_info['bookingSession']);
    }
}

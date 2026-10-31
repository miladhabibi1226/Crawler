<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;
use MatinUtils\NodeTracker\Models\City;
use MatinUtils\NodeTracker\Models\Country;

trait AddPassengers
{
    use BeforeBook;
    protected $responseSample = 'Samples/Navitaire/AddPassengers.json';
    public function sessionHandling()
    {
        return $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth;
        lugInfo('AddPassengers data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'timeout' => 20,
            'url' =>  $this->cleanUrlSlash($auth['url']) . "/api/jz/v1/Passengers",
            'apiMethod' => 'AddPassengers'
        ];
        return $this->action();
    }

    public function extractor()
    {
        preg_match_all('/"success":[\s]?false/', $this->rawResult['body'], $output_array);
        if (!empty($output_array[0])) {
            lugError('passenger info has problems', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            $this->addErrorToBook(trans('messages.unavailable'));
            return $this->addError('passenger info has problems');
        }
        // lugInfo('Navitaire Flight AddPassengers extractor Items', ['items' => $this->items]);
    }

    protected function getFields($depends)
    {
        $passengers = $this->object->passenger_info;
        $infants = $passengers['infants'] ?? [];
        unset($passengers['infants']);
        foreach ($passengers as $type => $typePassengers) {
            foreach ($typePassengers as $passengerIndex => $passenger) {
                $AirTraveler = [
                    "passengerKey" => $depends['passengersKeys'][$type][$passengerIndex],
                    "passengerInfo" => [
                        "customerNumber" => "",
                        "name" => [
                            "first" => $passenger['First_name'],
                            "middle" => "",
                            "last" => $passenger['Last_name'],
                            "title" => $passenger['title'],
                            "suffix" => ""
                        ],
                        "info" => [
                            "gender" => strtoupper($passenger['Gender']),
                            "dateOfBirth" => $passenger['Date of birth']
                        ]
                    ]
                ];
                if ($type == 'adults' && !empty($infants)) {
                    $infant = array_shift($infants);
                    $AirTraveler['infant'] = [
                        "gender" => strtoupper($infant['Gender']),
                        "dateOfBirth" => $infant['Date of birth'],
                        "name" => [
                            "first" => $infant['First_name'],
                            "middle" => "",
                            "last" => $infant['Last_name'],
                            "title" => $infant['title'],
                            "suffix" => ""
                        ]
                    ];
                }
                $AirTravelers[] = $AirTraveler;
            }
        }

        $fields = [
            "passengers" => $AirTravelers,
            "contact" => [
                "contactTypeCode" => "P",
                "phoneNumbers" => [
                    [
                        "type" => "Other",
                        "number" => $passengers['adults'][0]['Phone']['countryPhoneCode'] . '-' . $passengers['adults'][0]['Phone']['phone'],
                    ]
                ],
                "cultureCode" => "en-GB",
                "address" => [
                    "lineOne" => $passengers['adults'][0]['cityInfo']['en'],
                    "city" => $passengers['adults'][0]['cityInfo']['en'],
                    "countryCode" => Country::where('id', $passengers['adults'][0]['cityInfo']['country_id'])->select('abb')->first()->abb
                ],
                "emailAddress" => $passengers['adults'][0]['Email'],
                "distributionOption" => "Email",
                "customerNumber" => "",
                "sourceOrganization" => $depends['organization'],
                "companyName" => $depends['companyName'],
                "name" => [
                    "first" => $passengers['adults'][0]['First_name'],
                    "middle" => "",
                    "last" => $passengers['adults'][0]['Last_name'],
                    "title" => $passengers['adults'][0]['title'],
                    "suffix" => ""
                ],
            ]
        ];
        return $fields;
    }

    protected function handleSignOut()
    {
        $this->callSignOut($this->object->seller_info['bookingSession']);
    }
}

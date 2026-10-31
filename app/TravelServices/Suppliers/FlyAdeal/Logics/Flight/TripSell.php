<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics\Flight;

use App\TravelServices\Suppliers\Navitaire\Logics\Flight\TripSell as NavitaireTripSell;
use MatinUtils\NodeTracker\Models\Country;

trait TripSell
{
    use NavitaireTripSell;
    protected function getTripSellUrl()
    {
        return '/api/nsk/v4/trip';
    }

    protected function getRawResultUrl()
    {
        return $this->rawResult['decoded']['data'] ?? [];
    }

    protected function getPassengersKeys($Result)
    {
        foreach ($Result['passengers'] as $passenger) {
            $passengerType = ['ADT' => 'adults', 'CHD' => 'children', 'INF' => 'infants'][$passenger['passengerTypeCode']];
            $this->items['sellerInfo']['passengersKeys'][$passengerType][] = $passenger['passengerKey'];
        }
    }

    protected function getFields($depends)
    {
        foreach ($depends['bookingData'] as $data) {
            $keys[] = [
                "journeyKey" => $data['journeyKey'],
                "fareAvailabilityKey" => $data['fareAvailabilityKey'],
                "standbyPriorityCode" => '',
                "inventoryControl" => ''
            ];
        }

        $passengers = $this->object->passenger_info;
        $infants = $passengers['infants'] ?? [];
        unset($passengers['infants']);
        foreach ($passengers as $type => $typePassengers) {
            foreach ($typePassengers as $passenger) {
                $passengerInfo =  [
                    'passenger' => [
                        'name' => [
                            'first' =>  $passenger['First_name'],
                            'last' => $passenger['Last_name'],
                            'title' => $passenger['title'],
                            'suffix' => ''
                        ],
                        'discountCode' => "",
                        'info' => [
                            'nationality' => $passenger['CountriesInfo']['Nationality']['abb'],
                            "residentCountry" => $passenger['CountriesInfo']['Nationality']['abb'],
                            "dateOfBirth" => $passenger['Date of birth'],
                            "GENDER" => strtoupper($passenger['Gender']) == 'MALE' ? 1 : 2
                        ],
                        "TRAVELDOCUMENTS" => [
                            [
                                "DOCUMENTTYPECODE" => "P",
                                "BIRTHCOUNTRY" => $passenger['CountriesInfo']['Nationality']['abb'],
                                "ISSUEDBYCODE" => $passenger['CountriesInfo']['Nationality']['abb'],
                                "NAME" => [
                                    "FIRST" => $passenger['First_name'],
                                    "LAST" => $passenger['Last_name'],
                                    "TITLE" => $passenger['title'],
                                    "SUFFIX" => ""
                                ],
                                "NATIONALITY" => $passenger['CountriesInfo']['Nationality']['abb'],
                                "EXPIRATIONDATE" => $passenger['Passport Expiry Date'],
                                "NUMBER" => $passenger['Passport Number'],
                                "DATEOFBIRTH" => $passenger['Date of birth'],
                                "GENDER" => strtoupper($passenger['Gender']) == 'MALE' ? 1 : 2
                            ],
                            [
                                "DOCUMENTTYPECODE" => "N",
                                "BIRTHCOUNTRY" => $passenger['CountriesInfo']['Nationality']['abb'],
                                "NAME" => [
                                    "FIRST" => $passenger['First_name'],
                                    "LAST" => $passenger['Last_name'],
                                    "TITLE" => $passenger['title'],
                                    "SUFFIX" => ""
                                ],
                                "NATIONALITY" => $passenger['CountriesInfo']['Nationality']['abb'],
                                "ISSUEDBYCODE" => $passenger['CountriesInfo']['Nationality']['abb'],
                                "EXPIRATIONDATE" => $passenger['NationalId Expiry Date'],
                                "NUMBER" => $passenger['National Id'],
                                "DATEOFBIRTH" => $passenger['Date of birth'],
                                "GENDER" => strtoupper($passenger['Gender']) == 'MALE' ? 1 : 2
                            ]

                        ],
                        "passengerTypeCode" => ['adults' => 'ADT', 'children' => 'CHD'][$type],
                    ]
                ];
                if ($depends['isDomesticFlight'] && $passenger['CountriesInfo']['Nationality']['abb'] == 'SA') {
                    $passengerInfo['passenger']['TRAVELDOCUMENTS'][] =  [
                        "DOCUMENTTYPECODE" => "I",
                        "BIRTHCOUNTRY" => $passenger['CountriesInfo']['Nationality']['abb'],
                        "NAME" => [
                            "FIRST" => $passenger['First_name'],
                            "LAST" => $passenger['Last_name'],
                            "TITLE" => $passenger['title'],
                            "SUFFIX" => ""
                        ],
                        "NATIONALITY" => $passenger['CountriesInfo']['Nationality']['abb'],
                        "EXPIRATIONDATE" => $passenger['Iqama Expiry Date'],
                        "NUMBER" => $passenger['Iqama'],
                        "DATEOFBIRTH" => $passenger['Date of birth'],
                        "GENDER" => strtoupper($passenger['Gender']) == 'MALE' ? 1 : 2
                    ];
                }
                if ($type == 'adults' && !empty($infants)) {
                    $infant = array_shift($infants);
                    $passengerInfo['passenger']['infant'] = [
                        "gender" => strtoupper($infant['Gender']) == 'MALE' ? 1 : 2,
                        "dateOfBirth" => $infant['Date of birth'],
                        "name" => [
                            "first" => $infant['First_name'],
                            "middle" => "",
                            "last" => $infant['Last_name'],
                            "title" => $infant['title'],
                            "suffix" => ""
                        ],
                        'nationality' => $infant['CountriesInfo']['Nationality']['abb'],
                        "residentCountry" => $infant['CountriesInfo']['Nationality']['abb']
                    ];
                }
                $passengersInfo[] = $passengerInfo;
            }
        }

        $fields = [
            "journeys" => [
                "keys" => $keys,
                "preventOverlap" => true,
                "suppressPassengerAgeValidation" => true
            ],
            "contact" => [
                "cultureCode" => "en-GB",
                "address" => [
                    "lineOne" => $passengers['adults'][0]['cityInfo']['en'],
                    "countryCode" => Country::where('id', $passengers['adults'][0]['cityInfo']['country_id'])->select('abb')->first()->abb,
                    "city" =>  $passengers['adults'][0]['cityInfo']['en']

                ],
                "emailAddress" => $passengers['adults'][0]['Email'],
                "distributionOption" => 0,
                "notificationPreference" => 0,
                "name" => [
                    "first" =>  $passengers['adults'][0]['First_name'],
                    "last" => $passengers['adults'][0]['Last_name'],
                    "title" => $passengers['adults'][0]['title'],
                    "suffix" => ""
                ],
                "phoneNumbers" => [
                    [
                        "type" => 1,
                        "number" => $passengers['adults'][0]['Phone']['countryPhoneCode'] . '-' . $passengers['adults'][0]['Phone']['phone']
                    ]
                ]
            ],
            "passengers" => $passengersInfo,
            "currencyCode" => $depends['resultCurrency'],
            "SERVICEBUNDLECODES" => array_column($depends['bookingData'], 'bundleCode'),
            "applyServiceBundle" => 0
        ];
        return $fields;
    }

    protected function exchangeSellerPrices($amount)
    {
        ///> called in checkForPriceChange
        //> rewriting bc we don't have exchange in flyAdeal
        return $amount;
    }
}

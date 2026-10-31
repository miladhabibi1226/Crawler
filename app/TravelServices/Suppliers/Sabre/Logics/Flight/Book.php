<?php

namespace App\TravelServices\Suppliers\Sabre\Logics\Flight;

use App\TravelServices\Logics\Flight\Book as FlightBook;
use Carbon\Carbon;

trait Book
{
    protected $responseSample = 'Samples/Sabre/FlightBook.json';
    use FlightBook;
    public function sessionHandling()
    {
        return $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        $cardInformation = $this->paymentType();
        if (!empty($cardInformation)) {
            request()->merge(['cardInformation' => $cardInformation]);
        }

        $auth = $this->seller->api->configs;
        $depends = $this->getServiceInfo() + $auth + $this->object->seller_info + ['payable' => $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'], 'passengers' => $this->object->passenger_info];
        $depends['cardInformation'] = $cardInformation;
        $depends['paymentTypeFinal'] = empty($cardInformation) ? $depends['paymentType'] : (strtoupper($cardInformation['value']) == 'CASH' ? 'CASH' : 'CREDIT CARD');
        lugInfo('Book data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($auth['url']) . '/v2.4.0/passenger/records?mode=create',
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded']['CreatePassengerNameRecordRS'] ?? [];
        if (empty($BookResult) || empty($BookResult['ItineraryRef']['ID'])) {
            lugError('empty BookResult', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            $this->addError('empty result');
        }
        $this->checkForPriceChange($BookResult['AirPrice'][0]['PriceComparison']['AmountReturned'] ?? 0);

        $this->items['bookingInfo'] = [
            'pnr' => [$BookResult['ItineraryRef']['ID'] ?? ''],
            'airlinePnr' => $this->getPnrs($BookResult['TravelItineraryRead']['TravelItinerary']['ItineraryInfo']['ReservationItems']['Item'] ?? []),
            'expires_at' => $this->ticketTimeLimit()
        ];
        $this->items['sellerInfo'] = [
            'supplierReference' => $BookResult['ItineraryRef']['ID'] ?? '',
            'sabreStatus' => $BookResult['ApplicationResults']['status'] ?? null
        ];
        lugInfo('Sabre Flight Book extractor Items', ['items' => $this->items]);
    }

    protected function getPnrs($ReservationItems)
    {
        foreach ($ReservationItems as $ReservationItem) {
            if (empty($ReservationItem['FlightSegment'])) continue;
            foreach ($ReservationItem['FlightSegment'] as $segment) {
                $SupplierRef = explode('*', $segment['SupplierRef']['ID'] ?? '');
                if (!empty($SupplierRef[1])) {
                    $pnrs[] = $SupplierRef[1];
                }
            }
        }
        return array_unique($pnrs ?? []);
    }

    protected function getFields($depends)
    {
        $phoneCode = str_replace('+', '00', $depends['passengers']['adults'][0]['Phone']['countryPhoneCode']);
        $phoneNum = $depends['passengers']['adults'][0]['Phone']['phone'];
        $Email = $depends['passengers']['adults'][0]['Email'];
        $fields = [
            "CreatePassengerNameRecordRQ" => [
                "version" => "2.4.0",
                "targetCity" => $depends['pcc'],
                "haltOnAirPriceError" => true,
                "TravelItineraryAddInfo" => [
                    "AgencyInfo" => [
                        "Ticketing" => ["TicketType" => "7TAW"]
                    ],
                    "CustomerInfo" => [
                        "ContactNumbers" => [
                            "ContactNumber" => [
                                [
                                    "NameNumber" => "1.1",
                                    "Phone" => $phoneNum,
                                    "PhoneUseType" =>  "H"
                                ]
                            ]
                        ],
                        "Email" => [
                            [
                                "Address" => $Email,
                                "Type" =>  "CC"
                            ]
                        ],
                        "PersonName" => []
                    ]
                ],
                "AirBook" => [
                    "OriginDestinationInformation" => ["FlightSegment" => []],
                    "HaltOnStatus" => [
                        ["Code" => "HL"],
                        ["Code" => "HN"],
                        ["Code" => "KK"],
                        ["Code" => "LL"],
                        ["Code" => "NN"],
                        ["Code" => "NO"],
                        ["Code" => "UC"],
                        ["Code" => "US"],
                        ["Code" => "UN"]
                    ],
                    "RedisplayReservation" =>  ["NumAttempts" => 5, "WaitInterval" => 1000],
                    "RetryRebook" => ["Option" => true]
                ],
                "PostProcessing" => [
                    "EndTransaction" => ["Source" => ["ReceivedFrom" => str_contains($depends['url'], 'cert') ? "API TEST" : 'LUXOTA']],
                    "RedisplayReservation" => ["waitInterval" => 100],
                    "ARUNK" => ["priorPricing" => true],
                    "PostBookingHKValidation" => ["waitInterval" => 200, "numAttempts" => 4],
                    "WaitForAirlineRecLoc" => ["waitInterval" => 200, "numAttempts" => 4],
                ],
                "SpecialReqDetails" => [
                    "AddRemark" => ["RemarkInfo" => ["FOP_Remark" => ["Type" => $depends['paymentTypeFinal']]]],
                    "SpecialService" => [
                        "SpecialServiceInfo" => [
                            "AdvancePassenger" => [],
                            "SecureFlight" => [],
                            "Service" => [
                                [
                                    "SSR_Code" => "OSI",
                                    "Text" => "CTCM {$phoneCode}$phoneNum/EN",
                                    "VendorPrefs" => ["Airline" => ["Code" => "YY"]]
                                ],
                                [
                                    "SSR_Code" => "OSI",
                                    "Text" => "CTCE " . str_replace('@', '//',  $Email) . "/EN",
                                    "VendorPrefs" => ["Airline" => ["Code" => "YY"]]
                                ],
                                [
                                    "SegmentNumber" => "A",
                                    "SSR_Code" => "CTCM",
                                    "PersonName" => ["NameNumber" => '1.1'],
                                    "Text" => "{$phoneCode}{$phoneNum}"
                                ],
                                [
                                    "SegmentNumber" => "A",
                                    "SSR_Code" => "CTCE",
                                    "PersonName" => ["NameNumber" => '1.1'],
                                    "Text" => str_replace('@', '//',  $Email)
                                ]
                            ]
                        ]
                    ]
                ],
                "AirPrice" => [
                    [
                        "PriceComparison" => [
                            "AmountSpecified" => $depends['payable']
                        ],
                        "PriceRequestInformation" => [
                            "Retain" => true,
                            "OptionalQualifiers" => [
                                "PricingQualifiers" => ["PassengerType" => [["Code" => "ADT", "Quantity" => (string) $this->queryParams['adults']]]]
                            ]
                        ]
                    ]
                ]
            ]
        ];


        $passengersCounter = 1;
        foreach ($depends['passengers']['adults'] as $key => $passenger) {
            $title = strtoupper($passenger['Gender']) == 'MALE' ? 'Mr' : 'Ms';
            $givenName = "{$passenger['First_name']} $title";
            $fields['CreatePassengerNameRecordRQ']['TravelItineraryAddInfo']['CustomerInfo']['PersonName'][] = [
                "NameNumber" => "$passengersCounter.1",
                "PassengerType" => 'ADT',
                "GivenName" => $givenName,
                "Surname" => $passenger['Last_name'],
            ];
            $fields['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['AdvancePassenger'][] = [
                "SegmentNumber" => "A",
                "Document" => [
                    "ExpirationDate" => $passenger['Passport Expiry Date'],
                    "Number" => $passenger['Passport Number'],
                    "Type" => "P",
                    "IssueCountry" => $passenger['CountriesInfo']['Passport Issuing Country']['abb'],
                    "NationalityCountry" => $passenger['CountriesInfo']['Nationality']['abb']
                ],
                "PersonName" => [
                    "DateOfBirth" => $passenger['Date of birth'],
                    "Gender" => strtoupper($passenger['Gender'][0]),
                    "NameNumber" => "$passengersCounter.1",
                    "GivenName" => $givenName,
                    "Surname" => $passenger['Last_name'],
                ],
            ];
            $fields['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['SecureFlight'][] = [
                "SegmentNumber" => "A",
                "PersonName" => [
                    "DateOfBirth" => $passenger['Date of birth'],
                    "Gender" => strtoupper($passenger['Gender'][0]),
                    "NameNumber" => "$passengersCounter.1",
                    "GivenName" => $givenName,
                    "Surname" => $passenger['Last_name'],
                ],
            ];
            $passengersCounter++;
        }
        if (!empty($this->queryParams['children'])) {
            $fields['CreatePassengerNameRecordRQ']['AirPrice'][0]['PriceRequestInformation']['OptionalQualifiers']['PricingQualifiers']['PassengerType'][] = [
                "Code" => "CNN", /// for sabre it's either CNN or C09 where 09 is the age of the child
                "Quantity" => (string) $this->queryParams['children']
            ];
            foreach ($depends['passengers']['children'] as $key => $passenger) {
                $title = strtoupper($passenger['Gender']) == 'MALE' ? 'Mstr' : 'Miss';
                $givenName = "{$passenger['First_name']} $title";
                $fields['CreatePassengerNameRecordRQ']['TravelItineraryAddInfo']['CustomerInfo']['PersonName'][] = [
                    "NameNumber" => "$passengersCounter.1",
                    "PassengerType" => 'CNN',
                    "GivenName" => $givenName,
                    "Surname" => $passenger['Last_name'],
                ];
                $fields['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['AdvancePassenger'][] = [
                    "SegmentNumber" => "A",
                    "Document" => [
                        "ExpirationDate" => $passenger['Passport Expiry Date'],
                        "Number" => $passenger['Passport Number'],
                        "Type" => "P",
                        "IssueCountry" => $passenger['CountriesInfo']['Passport Issuing Country']['abb'],
                        "NationalityCountry" => $passenger['CountriesInfo']['Nationality']['abb']
                    ],
                    "PersonName" => [
                        "DateOfBirth" => $passenger['Date of birth'],
                        "Gender" => strtoupper($passenger['Gender'][0]),
                        "NameNumber" => "$passengersCounter.1",
                        "GivenName" => $givenName,
                        "Surname" => $passenger['Last_name'],
                    ],
                ];
                $fields['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['SecureFlight'][] = [
                    "SegmentNumber" => "A",
                    "PersonName" => [
                        "DateOfBirth" => $passenger['Date of birth'],
                        "Gender" => strtoupper($passenger['Gender'][0]),
                        "NameNumber" => "$passengersCounter.1",
                        "GivenName" => $givenName,
                        "Surname" => $passenger['Last_name'],
                    ]
                ];
                $fields['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['Service'][] = [
                    "SSR_Code" => "CHLD",
                    "PersonName" => [
                        "NameNumber" => "$passengersCounter.1",
                    ],
                    "Text" => Carbon::create($passenger['Date of birth'])->format('yMd'),
                ];
                $passengersCounter++;
            }
        }
        if (!empty($this->queryParams['infants'])) {
            $fields['CreatePassengerNameRecordRQ']['AirPrice'][0]['PriceRequestInformation']['OptionalQualifiers']['PricingQualifiers']['PassengerType'][] = [
                "Code" => "INF",
                "Quantity" => (string) $this->queryParams['infants']
            ];
            foreach ($depends['passengers']['infants'] as $key => $passenger) {
                $title = strtoupper($passenger['Gender']) == 'MALE' ? 'Mstr' : 'Miss';
                $givenName = "{$passenger['First_name']} $title";
                $fields['CreatePassengerNameRecordRQ']['TravelItineraryAddInfo']['CustomerInfo']['PersonName'][] = [
                    "NameNumber" => "$passengersCounter.1",
                    "PassengerType" => 'INF',
                    "Infant" => true,
                    "GivenName" => $givenName,
                    "Surname" => $passenger['Last_name'],
                ];
                $fields['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['AdvancePassenger'][] = [
                    "SegmentNumber" => "A",
                    "Document" => [
                        "ExpirationDate" => $passenger['Passport Expiry Date'],
                        "Number" => $passenger['Passport Number'],
                        "Type" => "P",
                        "IssueCountry" => $passenger['CountriesInfo']['Passport Issuing Country']['abb'],
                        "NationalityCountry" => $passenger['CountriesInfo']['Nationality']['abb']
                    ],
                    "PersonName" => [
                        "DateOfBirth" => $passenger['Date of birth'],
                        "Gender" => strtoupper($passenger['Gender'][0]) . "I",
                        "NameNumber" => ($key + 1) . ".1",
                        "GivenName" => $givenName,
                        "Surname" => $passenger['Last_name'],
                    ],
                ];
                $fields['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['SecureFlight'][] = [
                    "SegmentNumber" => "A",
                    "PersonName" => [
                        "DateOfBirth" => $passenger['Date of birth'],
                        "Gender" => strtoupper($passenger['Gender'][0]) . "I",
                        "NameNumber" => ($key + 1) . ".1", ///> acossiated adult's number
                        "GivenName" => $givenName,
                        "Surname" => $passenger['Last_name'],
                    ],
                ];
                $fields['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['Service'][] = [
                    "SSR_Code" => "INFT",
                    "PersonName" => [
                        "NameNumber" => ($key + 1) . ".1", ///> acossiated adult's number
                    ],
                    "Text" => "{$passenger['Last_name']}/{$givenName}/" . Carbon::create($passenger['Date of birth'])->format('yMd'),
                ];
                $passengersCounter++;
            }
        }
        $RPH = 1;
        foreach ($depends['legs'] as $legIndex => $leg) {
            foreach ($leg['segments'] as $segmentindex => $segment) {
                $fields['CreatePassengerNameRecordRQ']['AirBook']["OriginDestinationInformation"]['FlightSegment'][] = [
                    "DepartureDateTime" => str_replace('Z', '', Carbon::create($segment['departure']['raw_time'])->toIso8601ZuluString()),
                    "FlightNumber" => (string) $segment['flight_number'],
                    "ResBookDesigCode" => ($rbdc = strchr($segment['resBookDesigCode'], '-', true)) ?  $rbdc : $segment['resBookDesigCode'], ///> the part before - is rbdc and the part after it is brand name,
                    "DestinationLocation" => ["LocationCode" => $segment['arrival']['airport']['abb']],
                    "OriginLocation" => ["LocationCode" => $segment['departure']['airport']['abb']],
                    'NumberInParty' => (string) ((int) $this->queryParams['adults'] + (int) ($this->queryParams['children'] ?? 0)), ///> number of psngrs
                    "Status"  => "NN",
                    "MarketingAirline" => [
                        "Code" => $segment['airline']['supplier_key'],
                        "FlightNumber" => (string) $segment['flight_number']
                    ]
                ];
                $fields['CreatePassengerNameRecordRQ']['AirPrice'][0]['PriceRequestInformation']['OptionalQualifiers']['PricingQualifiers']['ItineraryOptions']['SegmentSelect'][] = [
                    "RPH" => (string) $RPH,
                    "Number" => (string) $RPH
                ];
                if (!is_int($segment['brand'])) {
                    ///> if brand is integer it means there are no brands available for this flights and we have used a ref number
                    $fields['CreatePassengerNameRecordRQ']['AirPrice'][0]['PriceRequestInformation']['OptionalQualifiers']['PricingQualifiers']['Brand'][] = [
                        "RPH" => $RPH,
                        "content" => $segment['brand']
                    ];
                    $brandExist = true;
                }
                $RPH++;
            }
        }
        if (!($brandExist ?? false)) {
            unset($fields['CreatePassengerNameRecordRQ']['AirPrice'][0]['PriceRequestInformation']['OptionalQualifiers']['PricingQualifiers']['ItineraryOptions']);
        }
        return $fields;
    }

    protected function failed($error = null)
    {
        if (empty($this->object->seller_info['supplierReference'])) {
            $this->object->seller_info = array_merge($this->object->seller_info ?? [], ['supplierReference' => $this->rawResult['decoded']['ContractInfo']['ContractNo'] ?? '']);
            $this->object->update();
        }
        return ['status' => false];
    }

    protected function ticketTimeLimit()
    {
        $date = $this->object->price_info['sellerPrice']['rawPrice']['lastTicketDate'] ?? '';
        $time = $this->object->price_info['sellerPrice']['rawPrice']['lastTicketTime'] ?? '';
        return "$date $time";
    }

    protected function paymentType()
    {
        $dataFromMSS = dataFromMSS('sabreBookPayment', ['seller' => $this->seller->id, 'serviceInfo' => $this->object->service_info]);
        return $dataFromMSS['cardInformation'] ?? [];
    }
}

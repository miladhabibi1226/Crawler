<?php

namespace App\TravelServices\Suppliers\Faranegar\Logics\Flight;

use App\TravelServices\Logics\Flight\Book as FlightBook;
use App\TravelServices\Logics\Flight\PassengersValidation as FlightPassengersValidation;
use App\TravelServices\Handler;

use Carbon\Carbon;

use function PHPSTORM_META\map;

trait Book
{
    use FlightBook;
    protected $responseSample = 'Samples/Faranegar/FlightBook.json';

    public function requestGenerator()
    {
        // $this->LugTheResponse = 1;
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->service_info + $this->object->seller_info + $this->object->price_info + ['passengers' => $this->object->passenger_info];
        lugInfo('Book data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($auth['url']) . '/Air_Book',
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
        $this->checkForPriceChange($BookResult['AirReservation']['PriceInfo']['ItinTotalFare']['TotalFare']['Amount'], $BookResult['PriceChange']);
        $this->items['bookingInfo'] = [
            'pnr' => (array) ($BookResult['AirReservation']['bookingReferenceID']['Id'] ?? null),
            'expires_at' => $BookResult['AirReservation']['bookingReferenceID']['Timelimit'] ?? null
        ];
        lugInfo('Faranegar Flight Book extractor Items', ['items' => $this->items]);
    }

    protected function getFields($depends)
    {
        ///> passengers info

        $passengerInfo = [];
        foreach ($depends['passengers'] as $passengerType => $passenger) {
            foreach ($passenger as $index => $type) {
                $passType = ($passengerType == "adults") ? "ADT" : ($passengerType == "children" ? "CHD" : "INF");
                $country = $this->getCountry($type['Nationality']);
                $passCountry = $this->getCountry($type['Passport Issuing Country']);
                $passengerInfo[] = [
                    "PersonName" => [
                        "GivenName" => $type['First_name'],
                        "Surname" => $type['Last_name'],
                        "NameTitle" => $type['title']
                    ],
                    "ContactInfo" => [
                        "PersonName" => [
                            "GivenName" => $type['First_name'],
                            "Surname" => $type['Last_name'],
                            "NameTitle" => $type['title']
                        ],
                        "Mobile" => [
                            "PhoneNumber" =>  substr($depends['passengers']['adults'][0]['Phone']['phone'], 3),
                            "CountryCode" => trim($depends['passengers']['adults'][0]["Phone"]["countryPhoneCode"], '+'),
                            "AreaCode" => substr($depends['passengers']['adults'][0]['Phone']['phone'], 0, 3)
                        ],
                    ],
                    "Document" => [
                        "DocType" => $this->needNationalId($country['abb']) ? "N" : "P",
                        "DocID" => $this->needNationalId($country['abb']) ? $type['National Id'] : $type['Passport Number'],
                        "ExpireDate" => $this->needNationalId($country['abb']) ? "" : $type['Passport Expiry Date'],
                        "DocIssueCountry" => $passCountry['abb'],
                        "Nationality" => $country['abb'],
                    ],
                    "BirthDate" => $type['Date of birth'],
                    "PassengerTypeCode" => $passType,
                    "Gender" => $type['Gender']
                ];
                if ($index == 0 && $passengerType == 'adults') {
                    $passengerInfo[0]['ContactInfo'] += [
                        "Email" => $depends['passengers']['adults'][0]['Email']
                    ];
                }
            }
        }
        ///> flight info
        $OrDe = [];
        foreach ($depends['legs'] as $key => $leg) {
            $OrDe[] = [
                "FlightSegment" => [
                    [
                        "FlightDuration" => $leg['info']['duration'],
                        "DepartureAirport" => [
                            "LocationCode" => $leg['info']['departure']['airport']['abb'],
                            "Terminal" => $leg['info']['departure']['terminal']
                        ],
                        "ArrivalAirport" => [
                            "LocationCode" => $leg['info']['arrival']['airport']['abb'],
                            "Terminal" => $leg['info']['arrival']['terminal']
                        ],
                        "Equipment" => [
                            "AirEquipType" => $leg['info']['airplane']
                        ],
                        "MarketingAirline" => [
                            "Code" => $leg['info']['airline']['abb']
                        ],
                        "BookingClassAvails" => [$depends['bookingData'][$key]],
                        "DepartureDateTime" => $leg['info']['departure']['raw_time'],
                        "ArrivalDateTime" => $leg['info']['arrival']['raw_time'],
                        "DepartureDate" => Carbon::parse($leg['info']['departure']['raw_time'])->format('Y-m-d'),
                        "DepartureTime" => Carbon::parse($leg['info']['departure']['raw_time'])->format('H:i'),
                        "ArrivalDate" => Carbon::parse($leg['info']['arrival']['raw_time'])->format('Y-m-d'),
                        "ArrivalTime" => Carbon::parse($leg['info']['arrival']['raw_time'])->format('H:i'),
                        "FlightNumber" => $leg['info']['flight_number'],
                        "ResBookDesigCode" => $depends['bookingData'][$key]['ResBookDesigCode'],
                        "RPH" => $depends['bookingData'][$key]['RPH']
                    ]
                ],
                "RefNumber" => $depends['bookingData'][$key]['RPH']
            ];
        }
        ///> price info
        $fareBreak = [];
        foreach ($depends['sellerPrice']['rawPrice']['PTC_FareBreakdowns'] as $fare) {
            $fareBreak[] = [
                "PassengerTypeQuantity" => [
                    "Code" => $fare['PassengerTypeQuantity']['Code'],
                    "Quantity" => $fare['PassengerTypeQuantity']['Quantity']
                ],
                "PassengerFare" => [
                    "BaseFare" => [
                        "Amount" => $fare['PassengerFare']['BaseFare']['Amount']
                    ],
                    "Taxes" => $fare['PassengerFare']['Taxes'],
                    "TotalFare" => [
                        "Amount" => $fare['PassengerFare']['TotalFare']['Amount']
                    ]
                ]
            ];
        }
        $price = [
            "ItinTotalFare" => [
                "BaseFare" => [
                    "Amount" => $depends['sellerPrice']['rawPrice']['ItinTotalFare']['BaseFare']['Amount']
                ],
                "TotalFare" => [
                    "Amount" => $depends['sellerPrice']['rawPrice']['ItinTotalFare']['TotalFare']['Amount']
                ],
                "TotalEquivFare" => [
                    "Amount" => $depends['sellerPrice']['rawPrice']['ItinTotalFare']['TotalFare']['Amount']
                ]
            ],
            "PTC_FareBreakDowns" => $fareBreak
        ];
        ///> result
        $fields = [
            "POS" => [
                "UserName" => $depends['username'],
                "Password" => $depends['password']
            ],
            "AirItinerary" => [
                "OriginDestinationOptions" => $OrDe
            ],
            "PriceInfo" => $price,
            "TravelerInfo" => $passengerInfo
        ];
        return $fields;
    }

    protected function needNationalId($country)
    {
        ///> check nationality again
        return ($this->object->seller_info['isIranianDomesticFlight'] && ($country ?? null) == 'IR');
    }

    protected function checkForPriceChange($totalPrice, $priceChange)
    {
        $searchTotalPrice = $this->object->price_info['sellerPrice']['rawPrice']['ItinTotalFare']['TotalFare']['Amount'];
        if (!$this->fromFile && $totalPrice != $searchTotalPrice && $priceChange != null) {
            lugError('Price Change', ['search' => $this->object->price_info['sellerPrice']['rawPrice'], 'result' => $totalPrice]);
            $this->addError("Price Has Changed");
            $this->failBook();
            $this->addErrorToBook(trans('messages.priceChanged'));
        }
    }
}

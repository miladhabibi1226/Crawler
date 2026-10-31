<?php

namespace App\TravelServices\Suppliers\CityNet\Logics\Flight;

use App\TravelServices\Logics\Flight\Book as FlightBook;
use Carbon\Carbon;

trait Book
{
    use FlightBook {
        FlightBook::failed as mainFailed;
    }
    protected $responseSample = 'Samples/CityNet/FlightBook.xml';

    public function sessionHandling()
    {
        return $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->seller_info + ['passengers' => $this->object->passenger_info];
        lugInfo('Book data', $depends);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($auth['url']) . '/flights/book',
            'apiMethod' => 'Book'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $BookResult = $this->rawResult['decoded'];

        $ContractInfo = $BookResult['ContractInfo'] ?? null;
        if (empty($ContractInfo)) {
            lugError('empty ContractInfo', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('empty result');
        }
        $this->checkForPriceChange($ContractInfo['Amount']);

        ///> for return flights the response is different for normal items and oneway combination items
        ///> but we have filtered oneWay combination items so here we are only handling normal flights
        $item = $BookResult['Items'][0] ?? [];
        $AirReservation = $item['Items']['AirReservation'];
        $SubsystemName = $this->object->seller_info['bookingRequestData'][0]['SubsystemName'] ?? '';
        $this->items['bookingInfo'] = [
            'pnr' => (array) ($AirReservation['Ticketing']['ID_Context'] ?? null),
            'expires_at' =>  $AirReservation['BookingReferenceID']['TicketTimeLimit'] ?? null
        ];

        $this->items['sellerInfo'] = [
            'issueRequestData' => ['ContractNo' => $ContractInfo['ContractNo'] ?? ''],
            'supplierReference' => ($ContractInfo['ContractNo'] ?? '') . " ($SubsystemName)"
        ];
        lugInfo('CityNet Flight Book extractor Items', ['items' => $this->items]);
    }

    protected function getFields($depends)
    {
        $fields = [
            'Discount' => [
                'CoponCode' => null
            ],
            'Owner' => [
                'Contacts' => [
                    [
                        'Email' => $depends['passengers']['adults'][0]['Email'],
                        'Telephone' => [
                            'PhoneTechType' => "Mobile",
                            'PhoneNumber' => $depends['passengers']['adults'][0]['Phone']['phone'],
                            'CountryAccessCode' => $depends['passengers']['adults'][0]['Phone']['countryPhoneCode'],
                            'AreaCityCode' => "",
                        ]
                    ]
                ]
            ],
            'Transports' => [
                'AirItinerary' => $depends['bookingRequestData'],
                'Ticketing' => [
                    'TicketType' => 'BookingOnly'
                ],
                'Captcha' => [$depends['captchaValue'] ?? null],
                'TravelerInfo' => [
                    'AirTraveler' => []
                ]
            ],
            'currencyToPay' => $this->seller->currency->abb
        ];

        foreach ($depends['passengers'] as $type => $typePassengers) {
            foreach ($typePassengers as $passenger) {
                $AirTraveler = [
                    "BirthDate" => $passenger['Date of birth'],
                    "PassengerTypeCode" => ['adults' => 'ADT', 'children' => 'CHD', 'infants' => 'INF'][$type],
                    "PersonName" => [
                        "GivenName" => $passenger['First_name'],
                        "NamePrefix" => strtoupper($passenger['title']),
                        "Surname" => $passenger['Last_name'],
                    ],
                    'NationalId' => ($passenger['CountriesInfo']['Nationality']['abb'] ?? '') == 'IR' ? $passenger['National Id'] : $passenger['Passport Number'] ?? ''
                ];

                if ($passenger['CountriesInfo']['Nationality']['abb'] == 'IR') {
                    ///> if the passenger is irani and the flight is domestic we don't have doc info, but we can't not send it so we use national id and a random date (cityNets technical team told us to do so)
                    $AirTraveler['Document'] = [ ///> if not isIranianDomesticFlight, documents will be rewritten later
                        "ExpireDate" => Carbon::tomorrow()->format('Y-m-d'),
                        "DocID" =>  $passenger['National Id'] ?? '',
                        "DocIssueCountry" =>  'IR',
                        "InnerDocType" => "Passport"
                    ];
                }
                if (!$depends['isIranianDomesticFlight'] || ($passenger['CountriesInfo']['Nationality']['abb'] ?? '') != 'IR') {
                    $AirTraveler['Document'] = [
                        "ExpireDate" => $passenger['Passport Expiry Date'] ?? '',
                        "DocID" => $passenger['Passport Number'] ?? '',
                        "DocIssueCountry" =>  $passenger['CountriesInfo']['Nationality']['abb'] ?? '',
                        "InnerDocType" => "Passport"
                    ];
                }
                $fields['Transports']['TravelerInfo']['AirTraveler'][] = $AirTraveler;
            }
        }
        return $fields;
    }

    protected function checkForPriceChange($totalPrice)
    {
        $searchTotalPrice = $this->object->price_info['sellerPrice']['rawPrice']['ItinTotalFare']['TotalFare'];
        if (!$this->fromFile && $totalPrice != $searchTotalPrice) {
            lugError('Price Change', ['search' => $this->object->price_info['sellerPrice']['rawPrice'], 'result' => $totalPrice]);
            $this->addError("Price Has Changed");
            $this->failBook();
            $this->addErrorToBook(trans('messages.priceChanged'));
        }
    }

    protected function failed($error = null)
    {
        if (empty($this->object->seller_info['supplierReference'])) {
            $this->object->seller_info = array_merge($this->object->seller_info ?? [], ['supplierReference' => $this->rawResult['decoded']['ContractInfo']['ContractNo'] ?? '']);
            $this->object->update();
        }
        return $this->mainFailed($error);
    }
}

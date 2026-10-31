<?php

namespace App\TravelServices\Suppliers\Sabre\Logics\Flight;

use App\TravelServices\Helpers\QueryParams;

trait RevalidateFlight
{
    use QueryParams;
    public function canBeDone()
    {
        $this->authorizeRequest();
        if (!$this->translatedQueryParams = $this->translateQueryParams()) {
            return $this->addError('Query params translation failed');
        }
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->getServiceInfo() + $this->object->seller_info + $auth;
        lugInfo('Revalidate data', [$depends, $this->translatedQueryParams]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'timeout' => 20,
            'url' => $this->cleanUrlSlash($auth['url']) . "/v4/shop/flights/revalidate",
            'apiMethod' => 'validate'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $validateResult = $this->rawResult['decoded']['groupedItineraryResponse'];
        $pricingInformation = collect($validateResult['itineraryGroups'][0]['itineraries'][0]['pricingInformation'] ?? []);
        $pricingInformation = $pricingInformation->where('revalidated', true)->first();
        if (empty($pricingInformation)) {
            lugError('empty pricingInformation', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            $this->addErrorToBook(trans('messages.unavailable'));
            return $this->addError('empty pricingInformation');
        }
        $this->checkForPriceChange($pricingInformation['fare']['totalFare']['totalPrice']);
        $this->items = [];
        lugInfo('Sabre Flight validate extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['beforeBooks']['revalidateFlight'] = true;
        $this->object->seller_info = $sellerInfo;
        $this->object->update();
    }

    protected function failed($error = null)
    {
        $this->failBook();
        $this->expireSupplierCache();
        $err = [];
        foreach ($error ?? [] as $errorMessages) {
            if ($this->getErrorClass($errorMessages) != 'technical') {
                $err[] = $errorMessages;
            }
        }
        if (empty($this->getSupplierErrorMessage())) {
            $error = implode('. ', $err);
        } else {
            $error = $this->getSupplierErrorMessage();
        }
        return ['status' => false, 'message' => $error];
    }

    protected function getFields($depends)
    {
        foreach ($depends['legs'] as $legIndex => $leg) {
            $Flight = $Brands = [];
            foreach ($leg['segments'] as $segmentindex => $segment) {
                $Flight[] = [
                    "Number" => $segment['flight_number'],
                    "DepartureDateTime" => $segment['departure']['raw_time'],
                    "ArrivalDateTime" => $segment['arrival']['raw_time'],
                    "ClassOfService" => ($rbdc = strchr($segment['resBookDesigCode'], '-', true)) ?  $rbdc : $segment['resBookDesigCode'], ///> the part before - is rbdc and the part after it is brand name
                    "OriginLocation" => ["LocationCode" => $segment['departure']['airport']['abb']],
                    "DestinationLocation" => ["LocationCode" => $segment['arrival']['airport']['abb']],
                    "Airline" => [
                        "Operating" => $segment['operatingAirline']['supplier_key'],
                        "Marketing" => $segment['airline']['supplier_key']
                    ]
                ];
                if (!is_int($segment['brand'])) {
                    ///> if brand is integer it means there are no brands available for this flights and we have used a ref number
                    $Brands[] = [
                        "Code" => $segment['brand'],
                        "PreferLevel" => 'Preferred',
                    ];
                }
            }
            $OriginDestinationInformation[] = [
                "DepartureDateTime" => $this->translatedQueryParams['legs'][$legIndex]['departure'] . 'T00:00:00',
                "DestinationLocation" => ["LocationCode" => $this->translatedQueryParams['legs'][$legIndex]['destination']['abb']],
                "OriginLocation" => ["LocationCode" => $this->translatedQueryParams['legs'][$legIndex]['origin']['abb']],
                "TPA_Extensions" => ["Flight" => $Flight] + (empty($Brands) ? []: ["BrandFilters" => ["Brand" => $Brands]]),
                "RPH" => (string) $legIndex
            ];
        }
        $fields = [
            "OTA_AirLowFareSearchRQ" => [
                "AvailableFlightsOnly" => true,
                "TravelPreferences" => [
                    "TPA_Extensions" => [
                        "VerificationItinCallLogic" => [
                            "Value" => "M",
                            "AlwaysCheckAvailability" => true
                        ]
                    ]
                ],
                "TravelerInfoSummary" => [
                    "PriceRequestInformation" => ["TPA_Extensions" => ["BrandedFareIndicators" => ["SingleBrandedFare" => true, "ReturnBrandAncillaries" => true]]],
                    "AirTravelerAvail" => [
                        [
                            "PassengerTypeQuantity" => [
                                [
                                    "Code" => "ADT",
                                    "Quantity" => (int) $this->translatedQueryParams['adults']
                                ]
                                ///> chilren and infants are added later because 0 is not accepted
                            ]
                        ]
                    ],
                    "SeatsRequested" => [
                        (int) ($this->translatedQueryParams['adults'] + $this->translatedQueryParams['children'])
                    ]
                ],
                "POS" => [
                    "Source" => [
                        [
                            "PseudoCityCode" => $depends['pcc'],
                            "RequestorID" => [
                                "CompanyName" => [
                                    "Code" => "TN"
                                ],
                                "ID" => "1",
                                "Type" => "1"
                            ]
                        ]
                    ]
                ],
                "OriginDestinationInformation" => $OriginDestinationInformation,
                "Version" => "3"
            ]
        ];
        if (!empty($this->translatedQueryParams['children'])) {
            $fields['OTA_AirLowFareSearchRQ']['TravelerInfoSummary']['AirTravelerAvail'][0]['PassengerTypeQuantity'][] = [
                "Code" => "CNN", /// for sabre it's either CNN or C09 where 09 is the age of the child
                "Quantity" => (int) $this->translatedQueryParams['children']
            ];
        }
        if (!empty($this->translatedQueryParams['infants'])) {
            $fields['OTA_AirLowFareSearchRQ']['TravelerInfoSummary']['AirTravelerAvail'][0]['PassengerTypeQuantity'][] = [
                "Code" => "INF",
                "Quantity" => (int) $this->translatedQueryParams['infants']
            ];
        }
        return $fields;
    }
}

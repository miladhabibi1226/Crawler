<?php

namespace App\TravelServices\Suppliers\Sepehr\Logics\Flight\S8B9;

use App\TravelServices\Suppliers\Sepehr\Logics\Flight\Search as CommonSepehrSearch;
use Carbon\Carbon;
use stdClass;

trait Search
{
    use CommonSepehrSearch;

    protected function canBeDone()
    {
        if (!empty($this->object->query_params['legs'][1])) {
            $this->addError("Sepehr S8B9 only provides one way trips");
        }
        $originCode = $this->object->query_params['legs'][0]['origin']['airport']['abb'] ?? $this->object->query_params['legs'][0]['origin']['city']['abb'];
        $destinationCode = $this->object->query_params['legs'][0]['destination']['airport']['abb'] ?? $this->object->query_params['legs'][0]['destination']['city']['abb'];
        if (!$this->isAvailable($originCode)) {
            $this->addError("Sepehr does not support the IATA code entered for origin airport: $originCode");
        }
        if (!$this->isAvailable($destinationCode)) {
            $this->addError("Sepehr does not support the IATA code entered for destination airport: $destinationCode");
        }
    }

    protected function getSearchUrl()
    {
        return '/api/Partners/Flight/Availability/V8/SearchByRouteAndDate';
    }

    protected function getSearchSampleAddress()
    {
        return  'Samples/Sepehr/FlightSearch.json';
    }

    public function requestGenerator()
    {
        ///> sepehr doesn't provide multiAirportCity search, so we use multi curl
        ///> even if both are airports we make only one call but via multiCurl

        $this->multi = true;
        $this->LugTheRequest = false;
        // $this->LugTheResponse = true;
        $auth = $this->seller->api->configs;
        $auth['password'] = hash('MD5', $auth['password']);
        $this->translatedQueryParams = $this->translateQueryParams(true);

        ///> only when multiAirportCity is chosen leg['origin']['airports'] exists. if it's empty leg['origin']['abb'] is the airport code
        $originAirports = $this->translatedQueryParams['legs'][0]['origin']['airports'] ?? [$this->translatedQueryParams['legs'][0]['origin']['abb']];
        $destinationAirports = $this->translatedQueryParams['legs'][0]['destination']['airports'] ?? [$this->translatedQueryParams['legs'][0]['destination']['abb']];
        // luginfo("Sepehr Search Data ({$this->seller->alias})", ['translatedQueryParams' => $translatedQueryParams, 'auth' => $auth]);

        foreach ($originAirports as $originAirport) {
            foreach ($destinationAirports as $destinationAirport) {
                ///> Sepehr only has oneway flights and roundtrip flights are failed before this, so only 1 leg in queryParams
                $this->translatedQueryParams['legs'][0]['origin']['abb'] = $originAirport;
                $this->translatedQueryParams['legs'][0]['destination']['abb'] = $destinationAirport;
                $this->prepareCalls($auth);
            }
        }
        return $this->action();
    }

    protected function getFields($depends)
    {
        $data = [
            'UserName' => $depends['username'],
            'Password' => $depends['password'],
            'OriginIataCode' => $depends['legs'][0]['origin']['abb'],
            'DestinationIataCode' => $depends['legs'][0]['destination']['abb'],
            'DepartureDate' => $depends['legs'][0]['departure'],
            'DepartureDateWindow' => 0,
            'FetchSupplierWebserviceFlights' => true,
            'Language' => 'EN',
        ];

        return $data;
    }

    protected function extractor()
    {
        foreach ($this->rawResult as $rawResult) {
            $charterFlights = $rawResult['decoded']['CharterFlights'] ?? [];
            $webserviceFlights = $rawResult['decoded']['WebserviceFlights'] ?? [];

            if (empty($charterFlights) && empty($webserviceFlights)) {
                ///> the results that have error
                continue;
            }

            if (($rawResult['decoded']['CurrencyCode'] ?? null) != $this->seller->currency->abb) {
                lugError('Currency Mismatch', ['result currency' => ($rawResult['decoded']['CurrencyCode'] ?? null), 'seller currency' => $this->seller->currency->abb]);
                $this->addError('Currency Mismatch');
                continue;
            }
            $flights[] = array_merge($charterFlights, $webserviceFlights);
        }
        if (empty($flights)) {
            $this->addError('No Result');
            return;
        }
        $combinedFlights = array_merge(...$flights);
        foreach ($combinedFlights as $itinerary) {
            foreach ($itinerary['Classes'] as $pricingClass) {
                $recommendations[] = [
                    'info' => $itinerary,
                    'pricingClass' => $pricingClass
                ];
            }
        }
        $this->extractSupplierCodes();
        $this->saveRawResponse();
        $this->isDomesticFlight = $this->isDomestic();
        $this->items = $this->itinerary($recommendations ?? []);
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = false;
        $info->afterSearch = [];
        $info->beforeBook = ['revalidate' => false];
        $info->extra = [
            'supplierVersion' => 'S8B9',
            'isDomesticFlight' => $this->isDomesticFlight,
            'bookingRequestData' => [
                "FlightNumber" => $itinerary['info']['FlightNumber'],
                "FlightDate" => $itinerary['info']['DepartureDateTime'],
                "OriginIataCode" => $itinerary['info']['Origin']['Code'],
                "DestinationIataCode" => $itinerary['info']['Destination']['Code'],
                "FareName" => $itinerary['pricingClass']['FareName'],
            ]
        ];
        return $info;
    }

    protected function legMapper($leg)
    {
        $segment = $leg;
        ///> sepehr used to be only one segment. now it's written in the docs that more segments might be in stop1 and stoop2 . but we won't handle it untill we see one
        if (!empty($leg['Stop1']) || !empty($leg['Stop2'])) {
            lugError('Unhandled segments', [$leg]);
            $this->callException('Unhandled segments');
        }

        $airline = $this->airlines[$segment['Airline']] ?? null;
        if (empty($airline)) { ///> means the airline is not in database
            $this->airlineSuppliers[] = [
                'supplier_key' => $segment['Airline'],
                'data_reference' => types('supplierDataReference', 'Sepehr')
            ];
            return false; ///> the result will be ignored if the airline doesn't exist
        }

        $capacities[] = $capacity = $this->currentClass['AvailableSeat'] ?? null;
        if ($capacity < ($this->passengersTypeCount['adults'] + $this->passengersTypeCount['children'])) {
            ///> comparing it w passengersTypeCount instead of modifiedPassengersTypeCount, bc if the capacity is suitable formodified count it still might be less than the actual count. forexample real count 410 modified 111 and capacity 3
            ///> there might be some issued w cached result but at the end we'll catch it in beforeBook
            return false;
        }

        $cabin = ['Economy' => 'Economy', 'PremiumEconomy' => 'Premium Economy', 'Business' => 'Business', 'First' => 'First'][$this->currentClass['CabinType'] ?? null] ?? null;
        if (strtoupper($cabin) != strtoupper($this->queryParams['cabin'])) {
            // lugWarning(
            //     'Cabin Mismatch',
            //     ['segment' =>$segment,'queryParams' => $this->queryParams ]
            // );
            return false;
        }

        $mins = Carbon::create($segment['DepartureDateTime'])->diffInMinutes(Carbon::create($segment['ArrivalDateTime']));
        $duration = intval($mins / 60) . ":" . ($mins % 60);

        $departureCity = $this->airports[$segment['Origin']['Code']]['city']['title'] ?? $segment['Origin']['Code'];
        $departureDateTime = $this->humanDateTime($segment['DepartureDateTime']);
        $departureAirport = $this->airports[$segment['Origin']['Code']] ?? $segment['Origin']['Code'];
        $departureTerminal = $segment['Origin']['Terminal'] ?? '-';

        $arrivalCity = $this->airports[$segment['Destination']['Code']]['city']['title'] ?? $segment['Destination']['Code'];
        $arrivalDateTime = $this->humanDateTime($segment['ArrivalDateTime']);
        $arrivalAirport = $this->airports[$segment['Destination']['Code']] ?? $segment['Destination']['Code'];
        $arivalTerminal = $segment['Destination']['Terminal'] ?? '-';

        $baggage = "--";
        $airplane = $this->airplanes[$segment['Aircraft']]['title'] ?? $segment['Aircraft'];
        $legFlightNumbers[] = $flightNumber = $segment['FlightNumber'];
        $afterFlight = $this->between2Time($segment['ArrivalDateTime'], null);
        $resBookDesigCode = $this->currentClass['BookingCode'];

        $newLeg = new stdClass();
        $newLeg->airlines = [];
        $newLeg->stops = 0;
        $newLeg->airlines[] = $airline['id'];
        $newLeg->segments = [[
            'departure' => [
                'city' => $departureCity,
                'time' => $departureDateTime['time'],
                'airport' => $departureAirport,
                'terminal' => $departureTerminal,
                'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                'raw_time' => $segment['DepartureDateTime']
            ],
            'arrival' => [
                'city' => $arrivalCity,
                'time' => $arrivalDateTime['time'],
                'airport' => $arrivalAirport,
                'terminal' => $arivalTerminal,
                'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                'raw_time' => $segment['ArrivalDateTime']
            ],
            'airline' => $airline,
            'cabin' => $cabin,
            'luggage' => $baggage,
            'airplane' => $airplane,
            'duration' => $duration,
            'ticket_type' => 'system',
            'flight_number' => $flightNumber,
            'after_flight_stop' => $afterFlight,
            'resBookDesigCode' => $resBookDesigCode,
            'capacity' => $capacity
        ]];
        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[0]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $cabin ?? '',
            'luggage' => '',
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' => $duration,
            'connections' => 0,
            'ticket_type' => 'system',
            'flight_number' => $newLeg->segments[0]['flight_number'],
            'after_flight_stop' => $newLeg->segments[0]['after_flight_stop'],
            'resBookDesigCode' => $resBookDesigCode,
            'capacity' => empty($capacities) ? '-' : min($capacities)
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = $resBookDesigCode;
        return $newLeg;
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass();
        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;

        ///> The price info in the response are for one adt one chd and one inf, regardless of the searched passenger type

        $breakDowns = ['adult' => $price['AdultFare']];
        if ($childCount > 0) {
            $breakDowns['child'] = $price['ChildFare'];
        }
        if ($infantCount > 0) {
            $breakDowns['infant'] = $price['InfantFare'];
        }
        $fligetBaseFare = $flightTotalFare = 0;
        foreach ($breakDowns as $passengerType => $priceDetails) {
            $typeCount = ${$passengerType . 'Count'};
            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['BaseFare'],
                'totalFare' => $priceDetails['TotalFare'],
                'tax' => $priceDetails['Tax'],
                'commission' => $priceDetails['Commision'],
                'payable' => $priceDetails['Payable'],
                'passengerTypeTotal' => [
                    'baseFare' => $fligetBaseFare += ($priceDetails['BaseFare'] * $typeCount),
                    'totalFare' => $priceDetails['TotalFare'] * $typeCount,
                    'tax' => $priceDetails['Tax'] * $typeCount,
                    'commission' => $priceDetails['Commision'] * $typeCount,
                    'payable' => $flightTotalFare += ($priceDetails['Payable'] * $typeCount),
                    'passengersCount' => $typeCount
                ]
            ];
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency; //> we have checked before in extractor(), so we are sure that at this stage the result currency is the seller currency
        $newPrice->baseFare = $fligetBaseFare;
        $newPrice->payable = $flightTotalFare;
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['info'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['info']['Airline'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['pricingClass'];
    }

    protected function prepareInfo($itinerary)
    {
        $this->currentClass = $itinerary['pricingClass'];
    }
}

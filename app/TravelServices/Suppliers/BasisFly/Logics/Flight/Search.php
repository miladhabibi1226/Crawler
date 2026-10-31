<?php

namespace App\TravelServices\Suppliers\BasisFly\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use stdClass;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;


trait Search
{
    use FlightSearch;
    protected $latestSession, $translatedQueryParams, $baggages;

    public function canBeDone()
    {
        $this->findOrCreateSupplierSession();
    }

    protected function requestGenerator()
    {
        $this->translatequeryParams = $this->translateQueryParams();
        $api = "/api-test/Flight_v2/Search";
        $this->call = [
            'fields' => json_encode($this->getFields()),
            'url' => $this->seller->api->configs['url'] . $api,
            'timeout' => 15,
            'apiMethod' => 'Search'
        ];

        return $this->action();
    }

    protected function getFields()
    {
        foreach ($this->translatequeryParams['legs'] as $legIndex => $leg) {
            $TripGroup[] = [
                "Origin" => $leg['origin']['abb'],
                "Destination" => $leg['destination']['abb'],
                "DepartureDate" =>  $leg['departure'],
            ];
        }
        $auth = $this->seller->api->configs;
        $data = [
            "ApiKey" => $auth['apiKey'],
            "SessionId" => $this->latestSession,
            "TripGroup" => $TripGroup,
            "CabinClass" => $this->translatequeryParams['cabin'],
            "Adults" => (string) $this->translatequeryParams['adults'],
            "Children" => (string) $this->translatequeryParams['children'],
            "Infants" => (string) $this->translatequeryParams['infants']
        ];

        return $data;
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded']['FlightProposals'];
        if (empty($rawResults)) {
            $this->addError('Empty Items');
            return;
        }
        if (!$this->checkCurrency($this->rawResult['decoded'])) {
            $this->addError('Currency Mismatch');
            return;
        }
        $this->saveRawResponse();

        $this->extractSupplierCodes();

        $this->isDomesticFlight = $this->isDomestic();

        $this->items = $this->itinerary($rawResults);
    }

    protected function extractSupplierCodes()
    {
        $this->airlines = $this->loadAirLines(array_keys($this->rawResult['decoded']['dictionaries']['carriers']));
        $this->airports = $this->loadAirports(array_keys($this->rawResult['decoded']['dictionaries']['location']));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['AirportCodes' => array_unique(array_merge($departureAirportCodes[1], $arrivalAirportCodes[1])), 'AirlineCode' => array_unique(array_merge($airlineCodes[1], $vAirlineCodes[1], $vAirlineCodes[1])), '$equipments' => $equipments, '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function checkCurrency($rawResults)
    {
        $currency = reset($rawResults['dictionaries']['currency']);
        if ($currency != $this->seller->currency->abb) {
            lugError('Currency Mismatch', ['sellerCurrency' => $this->seller->currency, 'Currency' =>  $currency ?? null]);
            return false;
        }
        return true;
    }

    protected function legMapper($leg)
    {
        $segments = $leg['RoutesInfo'];
        $cabin = $this->queryParams['cabin'];
        $newLeg = new stdClass();
        $newLeg->airlines = $newLeg->segments = $legFlightNumbers = $resBookDesigCodes = $capacities = [];
        $newLeg->stops = count($segments) - 1;
        foreach ($segments as $key => $segment) {
            $operatingAirline = $marketingAirline = $this->airlines[$segment['AirlineCode']] ?? null;
            if (empty($marketingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['AirlineCode'],
                    'data_reference' => types('supplierDataReference', 'BasisFly'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }
            $cabin = ['Economy' => 'Economy', 'BusinessClass' => 'Business', 'FirstClass' => 'First'][$segment['Class']] ?? null;
            if (!str_contains(strtoupper($cabin), strtoupper($this->queryParams['cabin']))) {
                return false;
            }

            $departureCity = $this->airports[$segment['OriginAirport']]['city']['title'] ?? '';
            $departureDateTime = $this->humanDateTime($segment['DepartureDate'] . 'T' . $segment['DepartureTime']);
            $departureAirport = $this->airports[$segment['OriginAirport']] ?? '';
            if (empty($departureAirport)) {
                lugWarning('DepartureAirport not found ' . ($segment['DestinationAirport'] ?? ''), [$segment]);
                return false;
            }
            $departureTerminal = '-';

            $arrivalCity = $this->airports[$segment['DestinationAirport']]['city']['title'] ?? '';
            $arrivalDateTime = $this->humanDateTime($segment['ArrivalDate'] . 'T' . $segment['ArrivalTime']);
            $arrivalAirport = $this->airports[$segment['DestinationAirport']] ?? '';
            if (empty($arrivalAirport)) {
                lugWarning('ArrivalAirport not found ' . ($segment['DestinationAirport'] ?? ''), [$segment]);
                return false;
            }
            $arrivalTerminal = '-';

            $baggageObject = $this->baggages->where('SegmentId', $segment['SegmentId'])->first() ?? "--";

            $baggage = $baggageObject['Baggage'] . $baggageObject['Unit'];
            $duration = $segment['Duration'];

            if (empty($segments[$key + 1])) {
                $dateTime = $segment['ArrivalDate'] . 'T' . $segment['ArrivalTime'];
            } else {
                $dateTime = ($segments[$key + 1]['DepartureDate'] . 'T' . $segments[$key + 1]['DepartureTime']);
            }

            $afterFlight = $this->between2Time($segment['ArrivalDate'] . 'T' . $segment['ArrivalTime'], $dateTime);

            $legFlightNumbers[] = $flightNumber = $segment['FlightNumber'];
            $resBookDesigCodes[] = $resBookDesigCode = $segment['ClassCode'];
            $capacities[] = $capacity = (int) $leg['AvailableSeats'] ?? null;
            $newLeg->airlines[] = $marketingAirline['id'];
            $newLeg->segments[$key] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'raw_time' =>  $segment['DepartureDate'] . 'T' . $segment['DepartureTime'],
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arrivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'raw_time' =>  $segment['ArrivalDate'] . 'T' . $segment['ArrivalTime'],
                ],
                'airline' => $marketingAirline,
                'operatingAirline' =>  $operatingAirline,
                'cabin' => $this->queryParams['cabin'],
                'luggage' => $baggage,
                'airplane' => '',
                'duration' => $duration,
                'ticket_type' => 'system',
                'flight_number' => $flightNumber,
                'after_flight_stop' => $afterFlight,
                'resBookDesigCode' => $resBookDesigCode,
                'capacity' => $capacity
            ];
        }

        $legDuration = $leg['Duration'];
        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[count($newLeg->segments) - 1]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $this->queryParams['cabin'],
            'luggage' => '',
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' => $legDuration,
            'connections' => count($segments) - 1,
            'ticket_type' => 'system',
            'flight_number' => implode('/', $legFlightNumbers),
            'after_flight_stop' => '-',
            'resBookDesigCode' => implode('/', $resBookDesigCodes),
            'capacity' => empty($capacities) ? '-' : min($capacities)
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = implode('/', $resBookDesigCodes);
        return $newLeg;
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass();
        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;
        foreach ($price['PassengerFare'] as $priceDetails) {
            if ($priceDetails['Count'] === 0) continue;
            $passengerType = strtolower($priceDetails['passengerType']);
            $typeCount = $priceDetails['Count'];
            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                // lugError($passengerType . ' count mismatch', ['passengersTypeCount' => $this->passengersTypeCount, 'result count' => $priceDetails['PassengerTypeQuantity']]);
            } else {
                ///> to check later that all the requested passengers types are available in the result
                ${$passengerType . 'Count'} = 'exists';
            }
            $tax = $priceDetails['Tax'] + $priceDetails['providerFare'];
            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['BaseFare'] / $typeCount,
                'tax' => $tax / $typeCount,
                'totalFare' => $priceDetails['Total'] / $typeCount,
                'commission' => $priceDetails['Commission'] / $typeCount,
                'payable' => $priceDetails['TotalCommission'] / $typeCount,
                'passengerTypeTotal' => [
                    'baseFare' => $priceDetails['BaseFare'],
                    'tax' => $tax,
                    'totalFare' => $priceDetails['Total'],
                    'commission' => $priceDetails['Commission'],
                    'payable' => $priceDetails['TotalCommission'],
                    'passengersCount' => $typeCount,
                ],
            ];
        }
        ///> checking if all the passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            return false;
        }
        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $price['BaseFare'];
        $newPrice->payable = $price['Total'];
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = false;
        $info->afterSearch = ['fareRules' => false];
        $info->beforeBook = [];
        $info->extra = [
            'isDomesticFlight' => $this->isDomesticFlight,
            'FlightId' =>  $itinerary['FlightId'] ?? '',
            'latestSession' => $this->latestSession
        ];

        return $info;
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['phone', 'text_area', 'email'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'passport_expiry', 'passport_number', 'nationality']
        ];
        $data = $this->addPassengerFormItem($items);

        if ($this->isDomesticFlight) {
            $data = $this->addPassengerFormItem(['all' => ['nationalId']], $data);
        }
        return $data;
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['FlightGroup'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['FlightGroup'][0]['RoutesInfo'][0]['AirlineCode'];
    }
    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['PriceInfo'];
    }
    protected function  prepareInfo($itinerary)
    {
        $this->baggages = collect($itinerary['Baggages']);
    }
}

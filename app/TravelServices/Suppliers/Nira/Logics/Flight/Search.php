<?php

namespace App\TravelServices\Suppliers\Nira\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use Carbon\Carbon;
use stdClass;

trait Search
{
    use FlightSearch;

    protected $Cabin = [];

    public function canBeDone()
    {
        ///> just OneWay Flights
        if (!$this->isOneWaySearch) {
            return $this->addError('Nira only supports OneWay flights');
        }
        return true;
    }

    protected function decodeRawResult()
    {
        ///> we used multiCurls so
        foreach ($this->rawResult as $index => $rawResult) {
            $this->rawResult[$index]['decoded'] = json_decode($rawResult['body'], true);
        }
    }

    public function requestGenerator()
    {
        // $this->LugTheResponse = 1;
        $queryParams = $this->translateQueryParams(true);
        luginfo("Nira Search Data ({$this->seller->alias})", ['translatedQueryParams' => $queryParams, 'auth' => $this->seller->api->configs]);
        $this->multi = true;
        ///>  multiAirportCity
        $originAirports = $queryParams['legs'][0]['origin']['airports'] ?? [$queryParams['legs'][0]['origin']['abb']];
        $destinationAirports = $queryParams['legs'][0]['destination']['airports'] ?? [$queryParams['legs'][0]['destination']['abb']];
        foreach ($originAirports as $originAirport) {
            foreach ($destinationAirports as $destinationAirport) {
                ///> Nira only has oneway flights and roundtrip flights are failed before this, so only 1 leg in queryParams
                $queryParams['legs'][0]['origin']['abb'] = $originAirport;
                $queryParams['legs'][0]['destination']['abb'] = $destinationAirport;
                $this->prepareCalls($this->seller->api->configs, $queryParams);
            }
        }
        return $this->action();
    }

    protected function prepareCalls($auth, $queryParams)
    {
        $query = http_build_query([
            "cbSource" => $queryParams['legs'][0]['origin']['abb'],
            "cbTarget" => $queryParams['legs'][0]['destination']['abb'],
            "cbAdultQty" => $queryParams['adults'],
            "cbChildQty" => $queryParams['children'],
            "cbInfantQty" => $queryParams['infants'],
            "OfficeUser" => $auth['officeUser'],
            "OfficePass" => $auth['officePass'],
            "DepartureDate" => $queryParams['legs'][0]['departure'],
            "Airline" => $this->Airline
        ]);
        $this->call[] = [
            'fields' => "",
            'url' => $this->cleanUrlSlash($auth['url']) . "/ws1/AvailabilityJS.jsp?{$query}",
            'method' => "GET",
            'timeout' => 20,
            'apiMethod' => 'Search'
        ];
    }

    protected function extractor()
    {
        foreach ($this->rawResult ?? [] as $thisRawResults) {
            if (!empty($thisRawResults['decoded']['AvailableFlights'])) {
                $rawResults[] = $thisRawResults;
            }
        }
        if (empty($rawResults)) {
            $this->addError('Empty Items');
            return;
        }
        if (!$this->checkCurrencyCode($rawResults)) {
            $this->addError('currency mismatch');
            lugWarning('currency mismatch', [$this->rawResult]);
            return;
        }
        $this->extractSupplierCodes();
        $this->isDomesticFlight = $this->isDomestic();
        $this->items = $this->itinerary($this->recomendations());
    }

    protected function checkCurrencyCode($rawResults)
    {
        if (($rawResults[0]['decoded']['AvailableFlights'][0]['CurrencyCode'] ?? null) != $this->seller->currency->abb) {
            lugError('currency mismatch', ['result' => $rawResults[0]['decoded']['AvailableFlights'][0]['CurrencyCode'] ?? null, 'seller' => $this->seller->currency->abb]);
            return false;
        }
        return true;
    }

    protected function recomendations()
    {
        foreach ($this->rawResult as $result) {
            $rawResults = $result['decoded']['AvailableFlights'] ?? [];
            if (empty($rawResults)) {
                continue;
            }
            foreach ($rawResults as $flight) {
                if ($flight['Transit'] || empty($flight['ClassesStatus'])) continue;
                ///> check departure date
                $flightDepartureDate = Carbon::parse($flight['DepartureDateTime'])->format('Y-m-d');
                if ($flightDepartureDate != $this->queryParams['legs'][0]['departure']) {
                    lugError('departure date mismatch', ['current flight' => $flightDepartureDate, 'query flight' => $this->queryParams['legs'][0]['departure']]);
                    continue;
                }
                $classesStatus = str_replace('/', '', $flight['ClassesStatus']);
                $allFlightClasses = explode(' ', $classesStatus);
                ///> check passengers count and flight capacity
                foreach ($allFlightClasses as $flightClass) {
                    $availableSeats = substr($flightClass, -1);
                    if ($availableSeats == 'C' || $availableSeats == 'X') {
                        continue;
                    }
                    $recs[] = $flight + ['selectedClass' => $flightClass];
                }
            }
        }
        return $this->getFareInfo($recs ?? []);
    }

    protected function getFareInfo($recomendation)
    {
        if (empty($recomendation)) return [];
        $this->saveRawResponse();
        $auth = $this->seller->api->configs;
        $this->multi = true;
        $this->call = [];
        foreach ($recomendation as $index => $flightRec) {
            $route = "{$flightRec['Origin']}-{$flightRec['Destination']}";
            $RDB = substr($flightRec['selectedClass'], 0, -1);
            $query = http_build_query([
                "Airline" => $this->Airline,
                "OfficeUser" => $auth['officeUser'],
                "OfficePass" => $auth['officePass'],
                "Route" => $route,
                "RBD" => $RDB,
                "FlightNo" => $flightRec['FlightNo']
            ]);
            $this->call[$index] = [
                'fields' => "",
                'url' => $this->cleanUrlSlash($auth['url']) . "/ws1/FareJS.jsp?{$query}",
                'method' => "GET",
                'timeout' => 10,
                'apiMethod' => 'Fare'
            ];
        }
        $this->action();
        foreach ($this->rawResult as $index => $rawFare) {
            $fare = json_decode(iconv("windows-1256", "utf-8", $rawFare['body']), true);
            if (empty($fare['AdultTotalPrice'])) {
                continue;
            }
            $recomendation[$index]['pricingInfo'] =  $fare;
        }
        foreach ($recomendation as $index => $flightRec) {
            if (empty($flightRec['pricingInfo'])) {
                unset($recomendation[$index]);
            }
        }
        return $recomendation ?? [];
    }

    protected function getFareRules($flight)
    {
        $fareRule = [];
        $fareRule[] = [
            "group" => [
                "airline" => "{$this->Airline}",
                "cities" => "{$flight[0]['Origin']}-{$flight[0]['Destination']}"
            ],
            "details" => [[
                "title" => "CRCNRules",
                "text" => $flight[0]['pricingInfo']['CRCNRules']
            ]]
        ];
        return $fareRule;
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->onholdable = true;
        $info->afterSearch = [];
        $info->beforeBook = [];
        $info->extra = [
            'isIranianDomesticFlight' => $this->isDomesticFlight
        ];
        return $info;
    }

    protected function priceMapper($price)
    {
        ///> passengers type and count does't exist in supplier response , so we can't check it
        $newPrice = new stdClass();
        $AdultCount = $this->modifiedPassengersTypeCount['adults'];
        $ChildCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $InfantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;
        $passengerType = ['Adult', 'Child', 'Infant'];
        $fligetBaseFare = $flightTotalFare = 0;
        foreach ($passengerType as $passenger) {
            $typeCount = ${$passenger . 'Count'};
            if ($typeCount == 0) {
                continue;
            }
            $tax = $price[$passenger . "TotalPrice"] - $price[$passenger . "Fare"];
            $newPriceBreakDowns[strtolower($passenger)]  = [
                'baseFare' => $price[$passenger . "Fare"],
                'tax' => $tax,
                'totalFare' => $price[$passenger . "TotalPrice"],
                'commission' => 0,
                'payable' => $price[$passenger . "TotalPrice"],
                'passengerTypeTotal' => [
                    'baseFare' => $fligetBaseFare += ($price[$passenger . "Fare"] * $typeCount),
                    'tax' => $tax * $typeCount,
                    'totalFare' => $price[$passenger . "TotalPrice"] * $typeCount,
                    'commission' => 0,
                    'payable' => $flightTotalFare += ($price[$passenger . "TotalPrice"] * $typeCount),
                    'passengersCount' => $typeCount
                ]
            ];
        }
        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $fligetBaseFare;
        $newPrice->payable = $flightTotalFare;
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function legMapper($leg)
    {
        $newLeg = new stdClass();
        $newLeg->stops = 0;
        $newLeg->segments = $newLeg->airlines = [];
        $marketingAirline = $this->airlines[$leg['Airline']] ?? Null;
        if (empty($marketingAirline)) { ///> means the airline is not in database
            $this->airlineSuppliers[] = [
                'supplier_key' => $leg['Airline'],
                'data_reference' => types('supplierDataReference', 'Nira')
            ];
            return false;
        }
        $operatingAirline = $this->airlines[$leg['OperatingAirline']] ?? Null;
        if (empty($operatingAirline)) { ///> means the airline is not in database
            $this->airlineSuppliers[] = [
                'supplier_key' => $leg['OperatingAirline'],
                'data_reference' => types('supplierDataReference', 'Nira')
            ];
            return false;
        }
        $classesStatus = $leg['selectedClass'];
        $availableSeats = substr($classesStatus, -1);
        $class = substr($classesStatus, 0, -1);
        $cabin = $this->flightClassSelection($class);
        if ($cabin == false) {
            lugWarning('invalid  or unknown ClassStatus', ['Airline' => $this->Airline, 'ClassStatus' => $class]);
            return false;
        }
        if (strtoupper($cabin) != strtoupper($this->queryParams['cabin'])) {
            lugWarning('cabin mismatch', ['request' => $this->queryParams['cabin'], 'result' => $cabin, 'segment' => $leg, 'class' => $class, 'flightClass' => $classesStatus]);
            return false;
        }

        $departureAirport = $this->airports[$leg['Origin']] ?? '';
        if (empty($departureAirport)) { ///> means the Airport is not in database
            lugWarning('DepartureAirport not found ' . ($leg['Origin'] ?? ''), []);
            return false;
        }

        $arrivalAirport = $this->airports[$leg['Destination']] ?? '';
        if (empty($arrivalAirport)) { ///> means the Airport is not in database
            lugWarning('ArrivalAirport not found ' . ($leg['Destination'] ?? ''), []);
            return false;
        }
        $departureCity = $departureAirport['city']['title'];
        $arrivalCity = $arrivalAirport['city']['title'];

        $departureDateTime = $this->humanDateTime($leg['DepartureDateTime']);
        $arrivalDateTime = $this->humanDateTime($leg['ArrivalDateTime']);
        $baggage = $leg['pricingInfo']['BaggageAllowanceWeight'];
        $airplane = $this->airplanes[$leg['AircraftTypeName']] ?? '';
        $duration = $this->between2Time($leg['DepartureDateTime'], $leg['ArrivalDateTime']);
        $flightNumber = $leg['FlightNo'];
        $resBookDesigCode = $class;
        $capacity = ($availableSeats == 'A') ? 9 : $availableSeats;
        $newLeg->airlines[] = $marketingAirline['id'];
        $newLeg->segments[] = [
            'departure' => [
                'city' => $departureCity,
                'time' => $departureDateTime['time'],
                'airport' => $departureAirport,
                'terminal' => '-',
                'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                'raw_time' => $leg['DepartureDateTime']
            ],
            'arrival' => [
                'city' => $arrivalCity,
                'time' => $arrivalDateTime['time'],
                'airport' => $arrivalAirport,
                'terminal' => '-',
                'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                'raw_time' => $leg['ArrivalDateTime']
            ],
            'airline' => $marketingAirline,
            'operatingAirline' => $operatingAirline,
            'cabin' => $cabin,
            'luggage' => $baggage,
            'airplane' => $airplane,
            'duration' => $duration,
            'ticket_type' => 'system',
            'flight_number' => $flightNumber,
            'after_flight_stop' => '-',
            'resBookDesigCode' => $resBookDesigCode,
            'capacity' => $capacity
        ];
        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[0]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $cabin,
            'luggage' => $baggage,
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' => $duration,
            'connections' => 0,
            'ticket_type' => 'system',
            'flight_number' => $flightNumber,
            'after_flight_stop' => '-',
            'resBookDesigCode' => $resBookDesigCode,
            'capacity' => $capacity
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = $resBookDesigCode;
        return $newLeg;
    }

    protected function extractSupplierCodes()
    {
        ///> find all the airports airlines and airplanes from the response in our database
        foreach ($this->rawResult as $rawResult) {
            if (empty($rawResult['decoded']['AvailableFlights'])) continue;
            preg_match_all('/Origin":"(\w+)"/', trim($rawResult['body']), $departureAirportCodes);
            preg_match_all('/Destination":"(\w+)"/', trim($rawResult['body']), $arrivalAirportCodes);
            preg_match_all('/OperatingAirline":"(\w+)"/', trim($rawResult['body']), $operatingAirlineCodes);
            preg_match_all('/AircraftTypeName":"(\w+)"/', trim($rawResult['body']), $equipments);
            $airportCodesDep = array_merge($airportCodesDep ?? [], $departureAirportCodes[1] ?? []);
            $airlineCodesArr = array_merge($airlineCodesArr ?? [], $arrivalAirportCodes[1] ?? []);
            $operateCodes = array_merge($operateCodes ?? [], $operatingAirlineCodes[1] ?? []);
            $equip = array_merge($equip ?? [], $equipments[1] ?? []);
        }
        $this->airlines = $this->loadAirLines(array_unique($operateCodes));
        $this->airports = $this->loadAirports(array_unique(array_merge($airportCodesDep, $airlineCodesArr)));
        $this->airplanes = $this->loadAirplanes(array_unique($equip));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult' => trim($this->rawResult['body']), 'airlines' => $operatingAirlineCodes[1], 'airports' => array_merge($departureAirportCodes[1], $arrivalAirportCodes[1]), 'airplanes' => $equipments[1], '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'nationality', 'nationalId', 'passport_number', 'passport_expiry', 'passport_country']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function haveSupplierResponseError()
    {
        if ($this->multi) {
            foreach ($this->rawResult as $rawResult) {
                $ch = $this->checkResult($rawResult);
                if (!empty($ch)) {
                    $checked[] = $ch;
                }
            }
        } else {
            $checked = $this->checkResult($this->rawResult);
        }
        $this->supplierConversaion($checked ?? null);
        return $checked ?? [];
    }

    protected function checkResult($rawResult)
    {
        $rawError = $rawResult['decoded']['AvailableFlights'] ?? null;
        if (empty($rawError)) {
            $errorMessage = $rawError["Message"] ?? "";
            lugWSResError("Nira response contains error ({$this->seller->alias})", ['supplierError: ' => $errorMessage ?? ($rawError ?? $this->rawResult)]);
            return  $errorMessage ?? "Undetected error";
        }
        if ($rawResult['http_code'] != 200) {
            // lugWSResError("Nira response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            return 'Nira response is invalid';
        }
        return false;
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary;
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['Airline'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['pricingInfo'];
    }


    protected function flightClassSelection($class)
    {
        $cabinClasses = [
            // 1. Aseman
            'EP'  => ['Business' => ['D', 'I', 'Z'], 'First' => ['F', 'A'], 'default' => 'Economy'],
            // 2. Taban
            'HH'  => ['Business' => ['C', 'J'], 'default' => 'Economy'],
            // 3. ATA
            'I3'  => ['default' => 'Economy'],
            // 4. Cacbian
            'CAC' => ['Business' => ['C'], 'default' => 'Economy'],
            // 5. Saha
            'IRZ' => ['Business' => ['D', 'I', 'Z'], 'default' => 'Economy'],
            // 6. Meraj
            'J1'  => ['Business' => ['Z', 'J', 'C', 'D', 'I'], 'default' => 'Economy'],
            // 7. Pars
            'PAP' => ['default' => 'Economy'],
            // 8. Poya
            'PYA' => ['default' => 'Economy'],
            // 9. Qeshm
            'QB'  => ['default' => 'Economy'],
            // 10. Varash
            'VRH' => ['Business' => ['J'], 'default' => 'Economy'],
            //11.kish
            'Y9' => ['Business' => ['C'], 'default' => 'Economy'],
            // 12. Zagros
            'ZV'  => ['Business' => ['D', 'I'], 'PremiumEconomy' => ['Z'], 'default' => 'Economy']
        ];
        if (isset($cabinClasses[$this->Airline])) {
            $rules = $cabinClasses[$this->Airline];
            foreach ($rules as $category => $classes) {
                if ($category !== 'default' && in_array($class, $classes, true)) {
                    return $category;
                }
            }
            return $rules['default'];
        }
        return false;
    }
}

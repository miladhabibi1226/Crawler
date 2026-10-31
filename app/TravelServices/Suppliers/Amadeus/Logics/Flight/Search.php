<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use Carbon\Carbon;
use stdClass;

trait Search
{
    use FlightSearch;

    protected $latestSession, $rawTimes = [];
    protected $method = 'Fare_MasterPricerTravelBoardSearch';

    public function requestGenerator()
    {
        // $this->LugTheResponse = 1;
        // $this->LugTheRequest = 1;
        $depends = $this->translateQueryParams() + $this->headerData() + ['include' => 'FlightSearch', 'sellerCurrency' => $this->seller->currency->abb];
        lugInfo("Amadeus Search Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.stateless', $depends)->render(),
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'timeout' => 20,
            'apiMethod' => 'Search'
        ];
        return $this->action();
    }

    protected function decodeRawResult()
    {
        $xml = preg_replace('/<(\/?)(\w+):(\w+)/', '<$1$3', $this->rawResult['body']);
        $xml = new \SimpleXMLElement($xml);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//Body')), true)[0] ?? [];
        $this->rawResult['header'] = json_decode(json_encode($xml->xpath('//Header')), true)[0] ?? [];
    }

    protected function extractor()
    {
        $rawResults = $this->rawResult['decoded']['Fare_MasterPricerTravelBoardSearchReply'] ?? [];
        if (empty($rawResults)) {
            $this->addError('Empty Fare_MasterPricerTravelBoardSearchReply');
            return;
        }
        if (!$this->fromFile && $this->currencyMismatch($rawResults)) {
            lugError('currency mismatch', ['result' => $rawResults['conversionRate']['conversionRateDetail'], 'seller' => strtoupper($this->seller->currency->abb)]);
            $this->addError('currency mismatch');
            return;
        }
        $this->extractSupplierCodes();
        $this->saveRawResponse('xml');

        $this->baggages = $this->baggageModifier($rawResults['serviceFeesGrp']);
        $this->flightIndexes = $this->flightIndexesModifier($rawResults['flightIndex']);
        $this->items = $this->itinerary($this->recommendationsModifier($rawResults['recommendation'] ?? []));
    }

    protected function sellerInfoMapper()
    {
        $info = new stdClass();
        $info->onholdable = true;
        $info->afterSearch = ['fareInformativePricing' => false, 'fareRules' => false];
        $info->beforeBook = ['sellFromRec' => false, 'addPNRElements' => false, 'createFOP' => false, 'pricePNR' => false];
        $info->extra = [
            'rawTimes' => $this->rawTimes
        ];
        return $info;
    }

    protected function baggageModifier($serviceFeesGrp)
    {
        $legsNumber = count($this->queryParams['legs']);
        $baggageRefs = $baggages = [];
        $serviceCoverageInfoGrps = $serviceFeesGrp['serviceCoverageInfoGrp'];
        $freeBagAllowanceGrps = $serviceFeesGrp['freeBagAllowanceGrp'];
        $this->repelSingleMode($serviceCoverageInfoGrps);
        $this->repelSingleMode($freeBagAllowanceGrps);
        foreach ($freeBagAllowanceGrps as $freeBagAllowanceGrp) {
            $unit = $freeBagAllowanceGrp['freeBagAllownceInfo']['baggageDetails']['quantityCode'] == 'N' ? 'piece' : $freeBagAllowanceGrp['freeBagAllownceInfo']['baggageDetails']['unitQualifier'];
            $baggageRefs[$freeBagAllowanceGrp['itemNumberInfo']['itemNumberDetails']['number']] =  "{$freeBagAllowanceGrp['freeBagAllownceInfo']['baggageDetails']['freeAllowance']} $unit";
        }
        foreach ($serviceCoverageInfoGrps as $serviceCoverageInfoGrp) {
            $key = $serviceCoverageInfoGrp['itemNumberInfo']['itemNumber']['number'];
            $serviceCovInfoGrp = $serviceCoverageInfoGrp['serviceCovInfoGrp'];
            $this->repelSingleMode($serviceCovInfoGrp);
            ///> there might be 1 serviceCovInfoGrp for each leg or one for all
            if (count($serviceCovInfoGrp) > $legsNumber) {
                lugError('serviceCovInfoGrp is more than legsNumber', [$serviceFeesGrp, $this->rawResult['body']]);
                $this->callException('unexpected response structure');
            }
            for ($i = 0; $i < $legsNumber; $i++) {
                $baggageKey = $serviceCovInfoGrp[$i]['refInfo']['referencingDetail']['refNumber'] ?? $serviceCovInfoGrp[0]['refInfo']['referencingDetail']['refNumber'];
                $baggages[$key][$i] = $baggageRefs[$baggageKey];
            }
        }
        return $baggages;
    }

    protected function flightIndexesModifier($flightIndexesRaw)
    {
        $this->repelSingleMode($flightIndexesRaw);
        foreach ($flightIndexesRaw as $legIndex => $flightIndex) {
            $groupOfFlights = $flightIndex['groupOfFlights'];
            $this->repelSingleMode($groupOfFlights);
            foreach ($groupOfFlights as $groupOfFlight) {
                $groupOfFlightref = $this->groupOfFlightref($groupOfFlight['propFlightGrDetail']['flightProposal']);
                $segments = $groupOfFlight['flightDetails'];
                $this->repelSingleMode($segments);
                $flightIndexes[$legIndex][$groupOfFlightref]['segments'] = $segments;
                $flightIndexes[$legIndex][$groupOfFlightref]['EFT'] = $this->getLegEFT($groupOfFlight['propFlightGrDetail']['flightProposal']);;
            }
        }
        return $flightIndexes;
    }

    protected function recommendationsModifier($rawRecommendations)
    {
        $this->repelSingleMode($rawRecommendations);
        $recommendations = [];
        foreach ($rawRecommendations as $recommendation) {
            $paxFareProduct = $recommendation['paxFareProduct'];
            $this->repelSingleMode($paxFareProduct);
            $codeShareDetails = $paxFareProduct[0]['paxFareDetail']['codeShareDetails'];
            $this->repelSingleMode($codeShareDetails);
            if (($codeShareDetails[0]['transportStageQualifier'] ?? null) != 'V') $this->callException('transportStageQualifier');
            ///> ///> there is one paxFareProduct for each psngr typ. we'll use adt's, since for cabinProduct and validating airline, it's the samee for all passengers
            $fareDetails = $paxFareProduct[0]['fareDetails']; ///> there is one fareDetails for each leg
            $this->repelSingleMode($fareDetails);
            foreach ($fareDetails as $legIndex => $legFareDetails) {
                $segmentfareInfo = $legFareDetails['groupOfFares']; ///> there is one groupOfFares for each segment
                $this->repelSingleMode($segmentfareInfo);
                $fareDetails[$legIndex] = array_column($segmentfareInfo, 'productInformation');
            }
            $rawPrice = ['recPriceInfo' => $recommendation['recPriceInfo'], 'paxFareProduct' => $recommendation['paxFareProduct']];
            unset($recommendation['recPriceInfo']);
            unset($recommendation['paxFareProduct']);
            $segmentFlightRefs = $recommendation['segmentFlightRef'];
            $this->repelSingleMode($segmentFlightRefs);
            $t = [];
            foreach ($segmentFlightRefs as $segmentFlightRef) { ///> different segmentFlightRefs in a recommendation are different recommendations who have the same fare/price info
                list($legs, $baggages) = $this->getLegsAndBaggeges($segmentFlightRef['referencingDetail']);
                if (empty($legs)) {
                    continue 2;
                }
                foreach ($legs as $legIndex => $leg) {
                    foreach ($leg['segments'] as $segIndex => $segment) {
                        ////> if there are more than one cabinProducts each of them create a new recommendation but for now in this phase we'll ignore and just implement using the first cabinProduct
                        $fareDetail = $fareDetails[$legIndex][$segIndex];
                        $fareDetail['cabinProduct'] = $fareDetail['cabinProduct'][0] ?? $fareDetail['cabinProduct']; ///> cancelling the extra cabinproducts if exist
                        $legs[$legIndex]['segments'][$segIndex]['fareDetails'] = $fareDetail;
                        $legs[$legIndex]['segments'][$segIndex]['baggages'] = $baggages[$legIndex] ?? '-';
                    }
                }
                $recommendations[] = [
                    'itemNumber' => $recommendation['itemNumber'],
                    'segmentFlightRef' => $segmentFlightRef,
                    'rawPrice' => $rawPrice,
                    'legs' => $legs,
                    'codeShareDetails' => $codeShareDetails[0]
                ];
            }
        }
        return $recommendations;
    }

    protected function getLegsAndBaggeges($segmentFlightRefs)
    {
        $this->repelSingleMode($segmentFlightRefs);
        $legs = $baggage = [];
        $legIndex = 0;
        foreach ($segmentFlightRefs as $segmentFlightRef) {
            if (strtoupper($segmentFlightRef['refQualifier']) == 'B') {
                $baggage = $this->baggages[$segmentFlightRef['refNumber']];
            } else if (strtoupper($segmentFlightRef['refQualifier']) == 'S') {
                $refNumber = $segmentFlightRef['refNumber'];
                if (empty($this->flightIndexes[$legIndex][$refNumber])) {
                    lugwarning('flightIndex not found', [$legIndex, $refNumber, $this->flightIndexes]);
                    lugWarning('amadeus body', [$this->rawResult['body']]);
                    return [null, null];
                }
                $legs[$legIndex] = $this->flightIndexes[$legIndex][$refNumber];
                $legIndex++;
            }
        }
        return [$legs, $baggage];
    }

    protected function groupOfFlightref($flightProposals)
    {
        $this->repelSingleMode($flightProposals);
        foreach ($flightProposals as $flightProposal) {
            if (count($flightProposal) === 1) {
                return $flightProposal['ref'];
            }
        }
    }

    protected function getLegEFT($flightProposals)
    {
        $this->repelSingleMode($flightProposals);
        foreach ($flightProposals as $flightProposal) {
            if (($flightProposal['unitQualifier'] ?? '') == 'EFT') {
                return $flightProposal['ref'];
            }
        }
        return null;
    }

    protected function legMapper($leg, $legIndex)
    {
        $segments = $leg['segments'];
        $cabinTypes = ['M' => 'Economy Standard', 'W' => 'PremiumEconomy', 'Y' => 'Economy', 'C' => 'Business', 'F' => 'First'];
        $legFlightNumbers = $resBookDesigCodes = [];
        $newLeg = new stdClass();
        $newLeg->airlines = [];
        $newLeg->stops = count($segments) - 1;
        $newLeg->segments = [];
        foreach ($segments as $key => $segmentInfo) {
            $segment = $segmentInfo['flightInformation'];
            if (strtoupper($segmentInfo['flightInformation']['addProductDetail']['electronicTicketing'] ?? '') != 'Y') {
                // lugWarning('not eticketable', ['segmentInfo' => $segmentInfo]);
                return false;
            }
            $operatingAirline = $this->airlines[$segment['companyId']['operatingCarrier'] ?? $segment['companyId']['marketingCarrier']] ?? null;
            if (empty($operatingAirline)) { ///> means the airline is not in database
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['companyId']['operatingCarrier'] ?? $segment['companyId']['marketingCarrier'],
                    'data_reference' => types('supplierDataReference', 'Amadeus'),
                ];
                return false; ///> the result will be ignored if the airline doesn't exist
            }

            $marketingAirline = $this->airlines[$segment['companyId']['marketingCarrier']] ?? null;
            if (empty($marketingAirline)) {
                $this->airlineSuppliers[] = [
                    'supplier_key' => $segment['companyId']['marketingCarrier'],
                    'data_reference' => types('supplierDataReference', 'Amadeus'),
                ];
                return false;
            }

            $segCabin = $cabinTypes[$segmentInfo['fareDetails']['cabinProduct']['cabin']] ?? '';
            if (strtoupper($this->queryParams['cabin']) != strtoupper($segCabin)) {
                if (!(strtoupper($this->queryParams['cabin']) == 'ECONOMY' && in_array($segmentInfo['fareDetails']['cabinProduct']['cabin'], ['M', 'W', 'Y']))) {
                    // lugWarning('amadeus cabin mismatch', ['segCabin' => $segCabin, 'queryParams' => $this->queryParams['cabin'], 'result' => $segmentInfo['fareDetails']['cabinProduct']['cabin']]);
                    return false;
                }
            }

            $departureCity = $this->airports[$segment['location'][0]['locationId']]['city']['title'] ?? $segment['location'][0]['locationId'];
            $arrivalCity = $this->airports[$segment['location'][1]['locationId']]['city']['title'] ?? $segment['location'][1]['locationId'];

            $departureDateTime = $this->humanDateTime(Carbon::createFromFormat('dmy-Hi', $this->rawTime($segment, 'dep')));
            $arrivalDateTime = $this->humanDateTime(Carbon::createFromFormat('dmy-Hi', $this->rawTime($segment, 'arr')));

            $departureAirport = $this->airports[$segment['location'][0]['locationId']] ?? $segment['location'][0]['locationId'];
            $arrivalAirport = $this->airports[$segment['location'][1]['locationId']] ?? $segment['location'][1]['locationId'];

            $departureTerminal = $segment['location'][0]['terminal'] ?? '-';
            $arivalTerminal = $segment['location'][1]['terminal'] ?? '-';

            $baggage = $segmentInfo['baggages'];
            $airplane = $this->airplanes[$segment['productDetail']['equipmentType']]['title'] ?? $segment['productDetail']['equipmentType'];
            $durations[] = $duration = $this->getDuration($leg, $key);

            $end = empty($segments[$key + 1]) ? $this->rawTime($segment, 'arr') : $this->rawTime($segments[$key + 1]['flightInformation'], 'dep');
            $durations[] = $afterFlight = $this->between2Time(
                Carbon::createFromFormat('dmy-Hi', $this->rawTime($segment, 'arr')),
                Carbon::createFromFormat('dmy-Hi', $end)
            );

            $legFlightNumbers[] = $flightNumber = $segment['flightOrtrainNumber'];
            $resBookDesigCodes[] = $resBookDesigCode = $segmentInfo['fareDetails']['cabinProduct']['rbd'];

            $newLeg->airlines[] = $marketingAirline['id'];

            $this->rawTimes[$legIndex][$key] = $segment['productDateTime'];
            $newLeg->segments[$key] = [
                'departure' => [
                    'city' => $departureCity,
                    'time' => $departureDateTime['time'],
                    'airport' => $departureAirport,
                    'terminal' => $departureTerminal,
                    'date_time' => "{$departureDateTime['dateTime']} ($departureCity local time)",
                    'rawTime' => Carbon::createFromFormat('dmy-Hi', $this->rawTime($segment, 'dep'))->format('Y-m-d H:i') ///> not very raw tho
                ],
                'arrival' => [
                    'city' => $arrivalCity,
                    'time' => $arrivalDateTime['time'],
                    'airport' => $arrivalAirport,
                    'terminal' => $arivalTerminal,
                    'date_time' => "{$arrivalDateTime['dateTime']} ($arrivalCity local time)",
                    'rawTime' => Carbon::createFromFormat('dmy-Hi', $this->rawTime($segment, 'arr'))->format('Y-m-d H:i')
                ],
                'airline' => $marketingAirline,
                'operatingAirline' => $operatingAirline,
                'cabin' => $segCabin,
                'luggage' => $baggage,
                'airplane' => $airplane,
                'duration' => $duration,
                'ticket_type' => 'system',
                'flight_number' => $flightNumber,
                'after_flight_stop' => $afterFlight,
                'resBookDesigCode' => $resBookDesigCode,
                'capacity' => null
            ];
        }
        $legDuration = empty($leg['EFT']) ? $this->sumDurations($durations) : substr_replace($leg['EFT'], ":", 2, 0);
        $newLeg->info = [
            'departure' => $newLeg->segments[0]['departure'],
            'arrival' => $newLeg->segments[count($newLeg->segments) - 1]['arrival'],
            'airline' => $newLeg->segments[0]['airline'],
            'cabin' => $segCabin,
            'luggage' => '',
            'airplane' => $newLeg->segments[0]['airplane'],
            'duration' => $legDuration,
            'connections' => count($segments) - 1,
            'ticket_type' => 'system',
            'flight_number' => implode('/', $legFlightNumbers),
            'after_flight_stop' => '-',
            'resBookDesigCode' => implode('/', $resBookDesigCodes),
            'capacity' => null
        ];
        $newLeg->departureTime = $newLeg->segments[0]['departure']['time'];
        $newLeg->resBookDesigCodes = implode('/', $resBookDesigCodes);
        return $newLeg;
    }

    protected function getDuration($leg, $key)
    {
        $segment = $leg['segments'][$key]['flightInformation'];
        $attributeDetails = $segment['attributeDetails'] ?? [];
        $this->repelSingleMode($attributeDetails);
        $eft = (collect($attributeDetails))->where('attributeType', 'EFT')->first()['attributeDescription'] ?? '';
        if (empty($eft)) {
            if (count($leg['segments']) == 1) {
                $eft = $leg['EFT'];
            }
            if (empty($eft)) {
                return $this->between2Time(
                    Carbon::createFromFormat('dmy-Hi', $this->rawTime($segment, 'dep')),
                    Carbon::createFromFormat('dmy-Hi', $this->rawTime($segment, 'arr'))
                );
            }
        }
        return substr_replace($eft, ":", 2, 0);
    }

    protected function priceMapper($source)
    {
        $newPrice = new stdClass();
        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;

        $breakDowns = $source['paxFareProduct'];
        $this->repelSingleMode($breakDowns);
        foreach ($breakDowns as $priceDetails) {
            $passengerType = ['ADT' => 'adult', 'CH' => 'child', 'INF' => 'infant'][$priceDetails['paxReference']['ptc']];
            $travellers = $priceDetails['paxReference']['traveller'];
            $this->repelSingleMode($travellers);
            $typeCount = count($travellers);

            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                lugError($passengerType . ' count mismatch', ['passengersTypeCount' => $this->passengersTypeCount, 'result count' => $priceDetails['paxReference']]);
                $this->addError($passengerType . ' count mismatch');
            } else {
                ///> to check later that all the requested passengers types are available in the result
                ${$passengerType . 'Count'} = 'exists';
            }

            $costs = $priceDetails['paxFareDetail'];
            $totalFareAmount = $costs['totalFareAmount'];
            $totalTaxAmount = $costs['totalTaxAmount'];
            $base = $totalFareAmount - $totalTaxAmount;


            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $base,
                'tax' => $totalTaxAmount,
                'totalFare' => $totalFareAmount,
                'commission' => 0,
                'payable' => $totalFareAmount,
                'passengerTypeTotal' => [
                    'baseFare' => $base * $typeCount,
                    'tax' => $totalTaxAmount * $typeCount,
                    'totalFare' => $totalFareAmount * $typeCount,
                    'commission' => 0,
                    'payable' => $totalFareAmount * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];
        }

        ///> checking if all the passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            $this->addError('passengers type mismatch');
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $source['recPriceInfo']['monetaryDetail'][0]['amount'] - $source['recPriceInfo']['monetaryDetail'][1]['amount'];
        $newPrice->payable = $source['recPriceInfo']['monetaryDetail'][0]['amount'];
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function extractSupplierCodes()
    {
        preg_match_all('/<locationId>(\w+)<\/locationId>/', $this->rawResult['body'], $airportCodes);
        preg_match_all('/Carrier>(\w+)</', $this->rawResult['body'], $airlineCodes);
        preg_match_all('/<equipmentType>(\w+)<\/equipmentType>/', $this->rawResult['body'], $equipments);
        $this->airlines = $this->loadAirLines(array_unique($airlineCodes[1]));
        $this->airports = $this->loadAirports(array_unique($airportCodes[1]));
        $this->airplanes = $this->loadAirplanes(array_unique($equipments[1]));
        // lugInfo("extractSupplierCodes ({$this->seller->alias})", ['$this->rawResult[body]' => $this->rawResult['body'], 'airlineCodes' => $airlineCodes[1], 'airports' => $airportCodes[1], 'airplanes' => $equipments[1], '$this->airlines' => $this->airlines, '$this->airports' => $this->airports, '$this->airplanes' => $this->airplanes]);
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'passport_expiry', 'passport_number', 'passport_country']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function rawTime($seg, $depOrArr)
    {
        if ($depOrArr == 'dep') {
            return $seg['productDateTime']['dateOfDeparture'] . '-' . $seg['productDateTime']['timeOfDeparture'];
        } else {
            return $seg['productDateTime']['dateOfArrival'] . '-' . $seg['productDateTime']['timeOfArrival'];
        }
    }

    protected function currencyMismatch($rawResults)
    {
        $conversionRateDetail = $rawResults['conversionRate']['conversionRateDetail'];
        $this->repelSingleMode($conversionRateDetail);
        return strtoupper($conversionRateDetail[0]['currency'] ?? '') != strtoupper($this->seller->currency->abb);
    }

    protected function originDestinationOption($itinerary)
    {
        return $itinerary['legs'];
    }

    protected function validatingAirlineCode($itinerary)
    {
        return $itinerary['codeShareDetails']['company'];
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['rawPrice'];
    }
}

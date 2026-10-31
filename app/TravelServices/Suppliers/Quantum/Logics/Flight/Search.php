<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use App\TravelServices\Suppliers\Quantum\Logics\Search as QuantumSearch;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\FlightBuffer;
use MatinUtils\NodeTracker\Models\ServiceInfo;
use stdClass;

trait Search
{
    use FlightSearch, QuantumSearch {
        FlightSearch::canBeDone insteadof QuantumSearch;
        FlightSearch::getContent insteadof QuantumSearch;
        FlightSearch::itinerary as mainItinerary;
    }
    protected $serviceInfos, $pricingConditions;
    public function getFields()
    {
        foreach ($this->queryParams['legs'] as $legIndex => $leg) {
            $legs[] = [
                "origin" => $leg['origin']['buffer']['id'],
                "destination" => $leg['destination']['buffer']['id'],
                "departure" => $leg['departure'],
            ];
        }
        $depends = [
            'requestType' => env('API_INTERNAL', true) ? 'internal' : 'internalWithData',
            'searcherIdentity' => $this->object->searcher_identity ?? '',
            'legs' => $legs,
            'adults' => $this->queryParams['adults'],
            'children' => $this->queryParams['children'],
            'infants' => $this->queryParams['infants'],
            'cabin' => lcfirst($this->queryParams['cabin']),
            'currency' => $this->seller->currency_id,
            'tripType' => $this->queryParams['tripType'],
            'charterOnly' => $this->queryParams['charterOnly'] ?? 'false'

        ];
        return $depends;
    }

    protected function extractor()
    {
        $rawResult = $this->rawResult['decoded']['data'] ?? [];
        if (!empty($rawResult[0]) && ($rawResult[0]['priceDetails']['currency']['id'] ?? 0) != $this->seller->currency_id) {
            lugError('Currency Mismatch', ['result' => $rawResult[0]['priceDetails']['currency']['id'], 'seller' => $this->seller->currency_id]);
            return $this->addError('Currency Mismatch');
        }
        $this->retrieveServiceInfo($rawResult); ///> for custom search
        $this->items = $this->itinerary($rawResult);
        if (empty($this->items)) {
            return $this->addError('No items from the supplier');
        }
    }

    protected function itinerary($rawResults)
    {
        if (!env('API_INTERNAL', true)) {
            ///> means the data is being provided from an apiOut outside of the network (with different database)
            ///> in this case we need to handle the full serviceInfo not just the reference 
            return $this->mainItinerary($rawResults);
        }
        $skippedByexception = 0;
        foreach ($rawResults as $itinerary) {
            $item = $itinerary;
            $item['pricingConditions']['sellerType'] = 'apiOut';
            $item['service'] = $this->originDestinationOption($itinerary);
            $pricingConditions = $item['pricingConditions'];
            if (!$this->checkAccessExceptions($pricingConditions, $this->getAccessExceptions())) {
                $skippedByexception++;
                continue;
            }
            if (!($item['price'] = $this->priceModifier($this->supplierRawPricing($itinerary), $pricingConditions))) continue;
            if (!($item['sellerInfo'] = $this->sellerInfoModifier($itinerary))) continue;
            $items[] = $item;
        }
        lugInfo("S>>>> Quantum search Extractor items - " . $this->seller->alias, ['skippedByexception' => $skippedByexception, 'supplier raw response items count' => count($rawResults), 'Inserted items' => count($items ?? []), 'new airline Suppliers' => $this->airlineSuppliers]);
        return $items ?? [];
    }

    public function prepareInfo($result)
    {
        $this->pricingConditions = $result['pricingConditions'];
    }

    protected function getPricingConditions($serviceInfo)
    {
        return $this->pricingConditions;
    }

    protected function supplierRawPricing($result)
    {
        return $result['priceDetails'];
    }

    protected function originDestinationOption($result)
    {
        return $result['serviceInfo'];
    }

    protected function validatingAirlineCode($result)
    {
        return $result['serviceInfo']['validatingAirline']['abb'];
    }

    protected function retrieveServiceInfo($result) ///> rewritten in custom search
    {}

    protected function checkValidatingAirlineCode($validdatingAirlineAbb)
    {
        return $this->filterZagrosAirline($this->airlines[$validdatingAirlineAbb] ?? []);
    }

    protected function legModifier($rawLegs, $validatingAirline)
    {
        return $rawLegs;
    }

    protected function sellerInfoMapper($itinerary)
    {
        $info = new stdClass();
        $info->sellerCode = "{$this->seller->abb}({$itinerary['sellerCode']})";
        $info->onholdable = $itinerary['onholdable'] ?? false;
        $info->afterSearch = $itinerary['afterSearch'] ?? ['fareRules' => false];
        if ($itinerary['hasBrands'] ?? false) {
            $info->afterSearch['brands'] = false;
        }
        $info->beforeBook = $itinerary['beforeBook'] ?? [];
        $info->addOns = $itinerary['addOns'] ?? [];
        $info->extra = [
            'bufferReferenceId' => $itinerary['referenceId'],
            'sellerCode' => "{$this->seller->abb}({$itinerary['sellerCode']})" ///> this overwrites the main one 
        ];
        return $info;
    }

    protected function getPassengersForm($itinerary)
    {
        return $this->updateOriginalPassengerForm($itinerary['passengersForm']);
    }

    protected function flightAirlines($serviceInfo)
    {
        $airlines = [];
        foreach ($serviceInfo['legs'] as $leg) {
            $airlines[] = implode(',', array_column(array_column($leg['segments'], 'airline'), 'id') ?? []);
        }
        return $airlines;
    }

    protected function priceMapper($priceDetails)
    {
        $newPrice = new stdClass();
        foreach ($priceDetails['breakDowns'] as $passengerType => $breakDown) {
            $typeCount = $breakDown['passengersCount'];
            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' =>  $breakDown['baseFare'],
                'tax' => $tx = $breakDown['tax'] + $breakDown['serviceCharge'],
                'totalFare' => $tf = $breakDown['totalFare'],
                'commission' => $cm = $breakDown['commission'],
                'payable' =>  $breakDown['payable'],
                'passengerTypeTotal' => [
                    'baseFare' => $breakDown['passengerTypeTotal']['baseFare'],
                    'tax' => $tx * $typeCount,
                    'totalFare' => $tf * $typeCount,
                    'commission' => $cm * $typeCount,
                    'payable' => $breakDown['passengerTypeTotal']['payable'],
                    'passengersCount' => $typeCount
                ]
            ];
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $priceDetails['baseFare'];
        $newPrice->payable = $priceDetails['payable'];
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function mapper()
    {
        $isOnholdable = $this->isOnholdable();
        $flights = [];
        $originalFlights = collect($this->rawResult['decoded']['data']);
        foreach ($this->items as $flight) {
            $originalFlight = $originalFlights->where('referenceId', $flight['sellerInfo']['bufferReferenceId'])->first();
            $serviceInfo = $flight['service'];
            if (!env('API_INTERNAL', true)) {
                ///> means the data is being provided from an apiOut outside of the network (with different database)
                ///> so we nee to insert serviceInfo intoo our db
                if (!empty($originalFlight['fareRules'])) {
                    $serviceInfoInsertable[] = [
                        'reference_id' => $originalFlight['serviceInforeference'],
                        'type' => 'fareRules',
                        'data' => json_encode($originalFlight['fareRules']),
                        'created_at' => Carbon::now()
                    ];
                }
                $serviceInfoInsertable[] = [
                    'reference_id' => $originalFlight['serviceInforeference'],
                    'type' => 'serviceInfo',
                    'data' => json_encode($this->modifyServiceInfo($serviceInfo)), ///> can't do modifyServiceInfo in the leg mapper bc when we are reading from cache "leg mapper" won't happen
                    'created_at' => Carbon::now()
                ];
            }
            $flights[] = [
                'reference_id' => createReferenceId(),
                'search_query_id' => $this->object->id,
                'seller_id' => $this->seller->id,
                'seller_info' => json_encode($flight['sellerInfo']),
                'service_info' => $originalFlight['serviceInforeference'],
                'price_info' => json_encode($flight['price']),
                'currency_id' => $this->queryParams['marketplace_currency']['id'],
                'total_b2b' => 0, ///> plugin will fill this
                'total_b2c' => 0, ///> plugin will fill this
                'is_charter' => false,
                'airlines' => $originalFlight['airlines'],
                'cabin_type' => types('flightCabin', lcfirst($this->queryParams['cabin'])),
                'departure_period' => $originalFlight['departurePeriod'],
                'return_period' => $originalFlight['returnPeriod'],
                'stops' => $originalFlight['stops'],
                'is_onholdable' => $originalFlight['onholdable'] && $isOnholdable,
                'checksum' => $originalFlight['checksum'],
                'created_at' => Carbon::now()
            ];
        }

        $flights = $this->calculatePriceforEachItem($flights);
        $flights = $this->addTags($flights);
        $start = microtime(true);
        foreach (array_chunk($serviceInfoInsertable ?? [], 200) as $insertables) {
            ServiceInfo::insert($insertables);
        }
        foreach (array_chunk($flights ?? [], 200) as $flightBuffers) {
            FlightBuffer::insert($flightBuffers);
        }
        lugInfo('S>>>> ' . count($flights) . ' items inserted for ' . $this->seller->alias . '. Time: ' . (microtime(true) - $start));
    }
}

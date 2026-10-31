<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;
use stdClass;

trait Search
{
    use FlightSearch {
        FlightSearch::priceModifier as mainPriceModifier;
    }

    protected $tripType, $latestSession, $priceRawResult, $translatedQueryParams, $JourneyDuration, $OriginDestinationInformations = [], $supplierRawResultItems = [], $originAirports, $destinationAirports, $baggageInfo = [];

    protected function sellerInfoMapper($itinerary)
    {
        ///> baggage info
        $baggageOption = $itinerary['baggageOption'];
        $baggageOption = strtoupper($baggageOption['baggageCode'] ?? 'NO BAG') ==  'NO BAG' ? ['NO BAG'] : $baggageOption;
        $baggageRequest = [];
        $OriginDestinationOption = $itinerary['priceQuoteResult']['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'];
        $this->repelSingleMode($OriginDestinationOption);
        if (!empty($baggageOption['baggageCode'])) {
            foreach ($OriginDestinationOption as $segment) {
                $c = 0;
                for ($i = 1; $i <= $this->queryParams['adults']; $i++) {
                    $c++;
                    $baggageRequest[] = [
                        'baggageCode' => $baggageOption['baggageCode'],
                        'TravelerRefNumber' => "A$c",
                        'FlightRPH' => $segment['FlightSegment']['@attributes']['RPH'],
                        'Departure' => $segment['FlightSegment']['@attributes']['DepartureDateTime'],
                        'FlightNumber' => $segment['FlightSegment']['@attributes']['FlightNumber']
                    ];
                }
                for ($i = 1; $i <= $this->queryParams['children']; $i++) {
                    $c++;
                    $baggageRequest[] = [
                        'baggageCode' => $baggageOption['baggageCode'],
                        'TravelerRefNumber' => "C$c",
                        'FlightRPH' => $segment['FlightSegment']['@attributes']['RPH'],
                        'Departure' => $segment['FlightSegment']['@attributes']['DepartureDateTime'],
                        'FlightNumber' => $segment['FlightSegment']['@attributes']['FlightNumber']
                    ];
                }
            }
        }
        ///> Extraxting the raw OriginDestinationOptions to use it in the book request
        $newRawODO = $this->prepareRawODO($itinerary);
        $info = new stdClass();
        $info->onholdable = false;
        $info->afterSearch = [];
        $info->beforeBook = ['priceQuote' => false];
        $info->extra = [
            'rawODO' => str_replace('ns1', 'ns2', $newRawODO),
            'session' => $itinerary['session'],
            'JSESSIONID' => $itinerary['JSESSIONID'],
            'flightNumbers' => $itinerary['flightNumbers'],
            'baggageRequest' => $baggageRequest ?? [],
            'bookingRequestData' => [
                'SequenceNumber' => $itinerary['priceQuoteResult']['@attributes']['SequenceNumber'],
            ]
        ];
        return $info;
    }

    protected function sendMultiCurlForPrice($JSESSIONID)
    {
        $this->initialTripType();
        $auth = $this->seller->api->configs;
        $this->collectOriginDestinationInformations();
        if (empty($this->OriginDestinationInformations)) {
            lugError('no OriginDestinationInformations for price');
            return;
        }
        foreach ($this->OriginDestinationInformations as $index => $flight) {
            $depends = $auth + $flight + ['tripType' => $this->airTripType, 'queryParams' => $this->translatedQueryParams, 'TransactionIdentifier' => $this->searchRawResult['session'] ?? ''];
            $calls[$index] = [
                'fields' => $this->minifyXML(view($this->getPriceViewName(), $depends)->render()),
                'url' => $auth['url'],
                'headers' => array_merge([
                    "Cookie:$JSESSIONID",
                ], $this->getExtraHeaders($auth)),
                'timeou' => 60,
                'apiMethod' => 'Price Quote'
            ];
        }
        $this->multi = true;
        $this->supplierRawResultItems = array_keys($this->OriginDestinationInformations); ///> for lug
        foreach (array_chunk($calls ?? [], 10, true) as $callsChuncked) {
            $this->call = $callsChuncked;
            $this->action();
            $responses[] = $this->rawResult;
        }
        $this->priceRawResult = array_merge(...$responses);
    }

    protected function removePriceResultsWithError()
    {
        $curlCount = count($this->rawResult);
        $num = 0;
        foreach ($this->rawResult ?? [] as $key => $priceQuotesResult) {
            $num++;
            $error = $this->haveSupplierResponseErrorNew($priceQuotesResult, ". PriceQuote curl $num of $curlCount");
            if (!empty($error)) {
                unset($this->rawResult[$key]);
                continue;
            }
            //> remove ns1: bc they caused error
            $xml = preg_replace('/ns1:/m', '', $priceQuotesResult['body']);
            $xml = new \SimpleXMLElement($xml);
            $this->rawResult[$key]['decoded'] = json_decode(json_encode($xml->xpath('//soap:Body')), true);
        }
    }

    protected function getBaggageForAllResults($JSESSIONID = null)
    {
        $finalBaggages = [];
        $baggages = [];
        $this->sendMultiCurlForBaggage($JSESSIONID);
        if (empty($this->rawBaggageResult)) {
            lugError('no rawBaggageResult');
            return;
        }
        $this->collectBaggageResult();
        foreach ($this->OriginDestinationInformations as $flightNumbers => $OriginDestinationInformation) {
            ///> $this->rawResult is now the result of PriceQuotes multi curl
            $OnDBaggageDetailsResponse = $this->rawBaggageResult[$flightNumbers]['decoded'][0]['AA_OTA_AirBaggageDetailsRS']['BaggageDetailsResponses']['OnDBaggageDetailsResponse'] ?? [];
            if (!empty($OnDBaggageDetailsResponse)) {
                $this->repelSingleMode($OnDBaggageDetailsResponse);
                foreach ($OnDBaggageDetailsResponse as $key => $OnDBaggageDetails) {
                    $baggageResult = $OnDBaggageDetails['Baggage'] ?? [[]];
                    $this->repelSingleMode($baggageResult);
                    foreach ($baggageResult as $baggageInfo) {
                        if (empty($baggageInfo['baggageCode'])) {
                            lugWarning('no baggageCode', [$baggageResult]);
                            continue 2;
                        }
                        $baggages[$key][$baggageInfo['baggageCode']] = ($baggages[$key][$baggageInfo['baggageCode']] ?? 0) + $baggageInfo['baggageCharge'];
                    }
                }
                if (!empty($baggages)) {
                    $firstLeg = array_pop($baggages);
                    foreach ($firstLeg as $code => $price) {
                        $everyLegHasIt = true;
                        foreach ($baggages as $legbaggages) {
                            if (empty($legbaggages[$code])) {
                                $everyLegHasIt = false;
                            }
                        }
                        if ($everyLegHasIt) {
                            $finalBaggages[] = [
                                'baggageCode' => $code,
                                'baggageCharge' => $price + array_sum(array_column($baggages, $code))
                            ];
                        }
                    }
                    $collectedBags = collect($finalBaggages);
                    if ($collectedBags->where('baggageCode', 'No Bag')->count() < 1 && $collectedBags->where('baggageCode', '0 Kg')->count() < 1) {
                        array_unshift($finalBaggages, ['baggageCode' => '', 'baggageCharge' => 0]);
                    }
                }
            }
            $this->OriginDestinationInformations[$flightNumbers]['baggageResult'] = $finalBaggages ?? [];
            $finalBaggages = [];
            $baggages = [];
        }
    }

    protected function sendMultiCurlForBaggage($JSESSIONID)
    {
        $auth = $this->seller->api->configs;
        if (empty($this->OriginDestinationInformations)) {
            lugError('no OriginDestinationInformations for baggae');
            return;
        }
        foreach ($this->OriginDestinationInformations as $flightNumber => $OriginDestinationInformation) {
            $JSESSIONID = $this->getJsessionid($JSESSIONID, $flightNumber);
            $this->priceRawResult[$flightNumber]['cookie'][0] = $JSESSIONID;
            $this->repelSingleMode($OriginDestinationInformation['priceQuoteResult']['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']);
            $depends = $auth + ['airlineCode' => $this->validatingAirlineCode(null), 'tripType' => $this->airTripType, 'queryParams' => $this->translatedQueryParams, 'TransactionIdentifier' => $this->getBaggageOrgDesInfoAndTransactionId($OriginDestinationInformation)['TransactionIdentifier'], 'OriginDestinationInformation' => $this->getBaggageOrgDesInfoAndTransactionId($OriginDestinationInformation)['OriginDestinationInformation']];
            $calls[$flightNumber] = [
                'fields' => $this->minifyXML(view('Accelaero.FlightBaggages', $depends)->render()),
                'url' =>  $auth['url'],
                'headers' => array_merge([
                    "Cookie:$JSESSIONID",
                ], $this->getExtraHeaders($auth)),
                'timeout' => 10,
                'apiMethod' => 'Flight Baggages'
            ];
        }
        $this->multi = true;
        $this->supplierRawResultItems = array_keys($this->OriginDestinationInformations); ///> for lug
        foreach (array_chunk($calls ?? [], 10, true) as $callsChuncked) {
            $this->call = $callsChuncked;
            $this->action();
            $responses[] = $this->rawResult;
        }
        $this->rawBaggageResult = array_merge(...$responses);
        $this->call = $this->rawResult = [];
    }

    protected function collectBaggageResult()
    {
        $curlCount = count($this->rawBaggageResult);
        $num = 0;
        foreach ($this->rawBaggageResult ?? [] as $key => $priceQuotesResult) {
            $num++;
            $error = $this->haveSupplierResponseErrorNew($priceQuotesResult, ". Baggage curl $num of $curlCount");
            if (!empty($error)) {
                unset($this->rawBaggageResult[$key]);
                continue;
            }
            //> remove ns1: bc they caused error
            $xml = preg_replace('/ns1:/m', '', $priceQuotesResult['body']);
            $xml = new \SimpleXMLElement($xml);
            $this->rawBaggageResult[$key]['decoded'] = json_decode(json_encode($xml->xpath('//soap:Body')), true);
        }
    }

    protected function priceModifier($rawPrices, $conditions)
    {
        if (!($mainPriceInfo = $this->mainPriceModifier($rawPrices, $conditions))) return false;
        if (!empty($this->additionalFees())) {
            $mainPriceInfo['rawPrices']['additionalFees'] = $this->additionalFees();
        }
        return $mainPriceInfo;
    }

    protected function priceMapper($price)
    {
        $newPrice = new stdClass();

        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;

        $breakDowns = $price['PTC_FareBreakdowns']['PTC_FareBreakdown'];
        $this->repelSingleMode($breakDowns);
        foreach ($breakDowns as $priceDetails) {
            $passengerType = ['ADT' => 'adult', 'CHD' => 'child', 'INF' => 'infant'][$priceDetails['PassengerTypeQuantity']['@attributes']['Code']];
            $typeCount = $priceDetails['PassengerTypeQuantity']['@attributes']['Quantity'];

            ///> checking if the requested passenger count is available in the result
            if ($typeCount != ${$passengerType . 'Count'}) {
                ///> accelaero has 2 types of PTC_FareBreakdowns.
                ///> it's mostly normal, one PTC_FareBreakdown for each passengerType
                ///> but sometimes it's one PTC_FareBreakdown for each passengers
                return $this->tryAnotherWayForPriceInfo($price);
                lugError($passengerType . ' count mismatch', ['passengersTypeCount' => $this->passengersTypeCount, 'query params' => $this->queryParams, 'result count' => $priceDetails['PassengerTypeQuantity']]);
                $this->addError($passengerType . ' count mismatch');
            } else {
                ///> to check later that all the requested passengers types are available in th eresult
                ${$passengerType . 'Count'} = 'exists';
            }

            $costs = $priceDetails['PassengerFare'];

            $supplierTaxes =  $costs['Taxes']['Tax'] ?? [];
            $this->repelSingleMode($supplierTaxes);
            $totalTax = array_sum(array_map(function ($tax) {
                return $tax['@attributes']['Amount'];
            }, $supplierTaxes));

            $supplierFees = $costs['Fees']['Fee'] ?? [];
            ///> adding baggage fees manually
            $this->repelSingleMode($supplierFees);
            $totalFees = array_sum(array_map(function ($fee) {
                return $fee['@attributes']['Amount'];
            }, $supplierFees));

            $additionalFees = $passengerType == 'infant' ? 0 : $this->additionalFees()['amount'] ?? 0;

            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' =>  $costs['BaseFare']['@attributes']['Amount'],
                'tax' => $tx = $totalTax + $totalFees + $additionalFees,
                'totalFare' => $tf = $costs['TotalFare']['@attributes']['Amount'] + $additionalFees,
                'commission' => 0,
                'payable' => $pb = $costs['TotalFare']['@attributes']['Amount'] + $additionalFees,
                'passengerTypeTotal' => [
                    'baseFare' => $costs['BaseFare']['@attributes']['Amount'] * $typeCount,
                    'tax' => $tx * $typeCount,
                    'totalFare' => $tf * $typeCount,
                    'commission' => 0,
                    'payable' => $pb * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];
            $totalAdditionalFees = ($totalAdditionalFees ?? 0) + ($additionalFees * $typeCount);
        }

        ///> checking if all the passenger types were present in the result
        if ($adultCount != 'exists' || !in_array($childCount, ['exists', 0]) || !in_array($infantCount, ['exists', 0])) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount]);
            $this->addError('passengers type mismatch');
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $price['ItinTotalFare']['BaseFare']['@attributes']['Amount'];
        $newPrice->payable = $price['ItinTotalFare']['TotalFare']['@attributes']['Amount'] + $totalAdditionalFees;
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function tryAnotherWayForPriceInfo($price)
    {
        $newPrice = new stdClass();

        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;

        $breakDowns = $price['PTC_FareBreakdowns']['PTC_FareBreakdown'];
        $this->repelSingleMode($breakDowns);
        foreach ($breakDowns as $priceDetails) {
            $passengerType = ['ADT' => 'adult', 'CHD' => 'child', 'INF' => 'infant'][$priceDetails['PassengerTypeQuantity']['@attributes']['Code']];
            $typeCount = $priceDetails['PassengerTypeQuantity']['@attributes']['Quantity'];
            ${$passengerType . 'CountResult'} = (${$passengerType . 'CountResult'} ?? 0) + $typeCount;

            $costs = $priceDetails['PassengerFare'];

            $supplierTaxes =  $costs['Taxes']['Tax'] ?? [];
            $this->repelSingleMode($supplierTaxes);
            $totalTax = array_sum(array_map(function ($tax) {
                return $tax['@attributes']['Amount'];
            }, $supplierTaxes));

            $supplierFees = $costs['Fees']['Fee'] ?? [];
            $this->repelSingleMode($supplierFees);
            $totalFees = array_sum(array_map(function ($fee) {
                return $fee['@attributes']['Amount'];
            }, $supplierFees));

            $originalBreakDown[$passengerType]  = [
                'baseFare' => ($originalBreakDown[$passengerType]['baseFare'] ?? 0) + $costs['BaseFare']['@attributes']['Amount'],
                'tax' => ($originalBreakDown[$passengerType]['tax'] ?? 0) + $totalTax + $totalFees,
                'totalFare' => ($originalBreakDown[$passengerType]['totalFare'] ?? 0) + $costs['TotalFare']['@attributes']['Amount'],
                'commission' => 0,
                'payable' => ($originalBreakDown[$passengerType]['payable'] ?? 0) + $costs['TotalFare']['@attributes']['Amount']
            ];
        }

        foreach ($originalBreakDown as $passengerType => $passengerTypePriceInfo) {
            $additionalFees = $passengerType == 'infant' ? 0 : $this->additionalFees()['amount'] ?? 0;
            $newPriceBreakDowns[$passengerType]  = [
                'passengerTypeTotal' => [
                    'baseFare' => $bf = $passengerTypePriceInfo['baseFare'],
                    'tax' => $tx = $passengerTypePriceInfo['tax'] + $additionalFees,
                    'totalFare' => $tf = $passengerTypePriceInfo['totalFare'] + $additionalFees,
                    'commission' => 0,
                    'payable' => $pb = $passengerTypePriceInfo['payable'] + $additionalFees,
                    'passengersCount' => ${$passengerType . 'CountResult'}
                ],
                'baseFare' => $bf / ${$passengerType . 'CountResult'},
                'tax' => $tx / ${$passengerType . 'CountResult'},
                'totalFare' => $tf / ${$passengerType . 'CountResult'},
                'commission' => 0,
                'payable' => $pb / ${$passengerType . 'CountResult'}
            ];
            $totalAdditionalFees = ($totalAdditionalFees ?? 0) + ($additionalFees * $typeCount);
        }

        ///> checking if all the passenger types were present in the result
        if ($adultCount != ($adultCountResult ?? 0) || $childCount != ($childCountResult ?? 0) || $infantCount != ($infantCountResult ?? 0)) {
            lugError('passengers type mismatch', ['requested' => $this->queryParams, 'modifiedPassengersTypeCount' => $this->modifiedPassengersTypeCount, 'result' => $price, 'adultCount' => $adultCount, 'childCount' => $childCount, 'infantCount' => $infantCount, 'adultCountResult' => $adultCountResult, 'childCountResult' => $childCountResult ?? 0, 'infantCountResult' => $infantCountResult ?? 0]);
            $this->addError('passengers type mismatch');
        }

        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $price['ItinTotalFare']['BaseFare']['@attributes']['Amount'];
        $newPrice->payable = $price['ItinTotalFare']['TotalFare']['@attributes']['Amount'] + $totalAdditionalFees;
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }

    protected function additionalFees() ///> re-written in custom search
    {
        //> we get the baggage options seperately so
        return [
            'amount' => $this->baggageInfo['baggageCharge'] ?? 0,
            'desc' => $this->baggageInfo['baggageCode'] ?? 'No Bag'
        ];
    }

    protected function getPassengersForm()
    {
        $items = [
            'lead' => ['email', 'phone', 'city', 'text_area'],
            'all' => ['gender', 'first_name', 'last_name', 'birthdate', 'passport_expiry', 'passport_number', 'passport_country', 'nationality']
        ];
        return $this->addPassengerFormItem($items);
    }

    protected function haveSupplierResponseErrorNew($rawResult, $message = '')
    {
        preg_match('/<ns1:Error([^s][^>]+)>/ims', $rawResult['body'], $rawErrors);
        if (!empty($rawErrors)) {
            // lugWSResError("Accelaero response contain error $message ({$this->seller->alias})", ['supplier-error: ' => $rawErrors[1] ?? $rawErrors, 'supplier response: ' => $rawResult['body']]);
            return $rawErrors[1] ?? $rawErrors;
        }
        if ($rawResult['http_code'] != 200) {
            // lugWSResError("Accelaero response is invalid $message ({$this->seller->alias})", ['supplier-response: ' => $rawResult]);
            return 'Invalid Response';
        }
        preg_match('/<!DOCTYPE html>/', $rawResult['body'], $rawError);
        if (!empty($rawError)) {
            // lugWSResError("Accelaero response is invalid ({$this->seller->alias})", ['supplier-response: ' => $rawResult['body']]);
            return 'invalid response';
        }
        return false;
    }

    protected function validatingAirlineCode($itinerary)
    {
        return array_keys($this->airlines)[0] ?? ''; //> the supplier works only for 1 airline so we know there will be only one airline in the result
    }

    protected function supplierRawPricing($itinerary)
    {
        return $itinerary['priceQuoteResult']['AirItineraryPricingInfo'];
    }

    protected function initializeDefaultCity()
    {
        static $defaultCity, $defaultCountry = [];
        if (empty($defaultCity[node()->id][$this->seller->id]) || $defaultCity[node()->id][$this->seller->id]['id'] != $this->seller->api->configs['passengerDefaultCity']) { ///> second condition is for when they update the seller's city
            $city = $this->getCityWithCountry($this->seller->api->configs['passengerDefaultCity']);
            $defaultCity[node()->id][$this->seller->id] = ['id' => $city['id'], 'title' => $city['title']];
            $defaultCountry[node()->id][$this->seller->id] = ['id' => $city['country']['id'], 'abb' => $city['country']['abb'], 'title' => $city['country']['title']];
        }
        return [$defaultCity[node()->id][$this->seller->id] ?? [], $defaultCountry[node()->id][$this->seller->id] ?? []];
    }
}

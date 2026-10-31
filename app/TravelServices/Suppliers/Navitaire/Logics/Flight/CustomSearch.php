<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics\Flight;

use App\TravelServices\Logics\Flight\CustomSearch as CommonCustomSearch;
use stdClass;

trait CustomSearch
{
    use Search, CommonCustomSearch {
        CommonCustomSearch::itinerary insteadof Search;
        CommonCustomSearch::done insteadof Search;
        CommonCustomSearch::failed insteadof Search;
    }

    protected function supplierRawPricing($itinerary)
    {
        $this->callBookingQuote($itinerary);
        return [$itinerary['fare'], $this->rawResult['decoded']['data']['breakdown']['passengerTotals']['infant'] ?? [], $this->rawResult['decoded']['data']['breakdown']['balanceDue'] ?? 0];
    }

    protected function callBookingQuote($itinerary)
    {
        $auth = $this->seller->api->configs;
        $depends = $itinerary + $auth + $itinerary;
        lugInfo('BookingQuote data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getBCFields($depends)),
            'url' =>  $this->cleanUrlSlash($auth['url']) . "/api/jz/v1/BookingQuote",
            'apiMethod' => 'BookingQuote'
        ];
        $this->action();
        return $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
    }

    protected function getBCFields($depends)
    {
        foreach ($depends['bookingData'] as $data) {
            $keys[] = [
                "journeyKey" => $data['journeyKey'],
                "fareAvailabilityKey" => $data['fareAvailabilityKey'],
                "standbyPriorityCode" => '',
                "inventoryControl" => ''
            ];
        }
        $fields = [
            "keys" => $keys,
            "passengers" => ["types" => [["type" => "ADT", "count" => $this->queryParams['adults']]]],
            "currencyCode" => $this->resultCurrency,
            "infantCount" => $this->queryParams['infants'],
            "promotionCode" => '',
            "sourceOrganization" => '',
        ];
        if (!empty($this->queryParams['children'])) {
            $fields['passengers']['types'][] = ["type" => "CHD", "count" => $this->queryParams['children']];
        }
        return $fields;
    }

    protected function priceMapper($priceInfo)
    {
        list($legsPrice, $infantPrice, $balanceDue) = $priceInfo;
        if (empty($balanceDue)) {
            ///> means the bookingQuote api failed
            return false;
        }
        ///> the prices for each leg comes seperately. infants' price don't come in search result at all
        ///> combining total and breakDowns of all the legs
        foreach ($legsPrice as $legIndex => $price) {
            $passengerFares = $price['extraInfo']['value']['fares'][0]['passengerFares'];
            foreach ($passengerFares as $fareInfo) {
                $breakDowns[$fareInfo['passengerType']] = [
                    "baseFare" => ($breakDowns[$fareInfo['passengerType']]['baseFare'] ?? 0) + ($addToRawPrice[$legIndex]['breakdowns'][$fareInfo['passengerType']]['baseFare'] = $this->exchangeSellerPrices($fareInfo['revenueFare'])),
                    "totalFare" => ($breakDowns[$fareInfo['passengerType']]['totalFare'] ?? 0) + ($addToRawPrice[$legIndex]['breakdowns'][$fareInfo['passengerType']]['totalFare'] = $this->exchangeSellerPrices($fareInfo['fareAmount']))
                ];
            }
            $total = [
                "baseFare" => ($total['baseFare'] ?? 0) + ($addToRawPrice[$legIndex]['baseFare'] = $this->exchangeSellerPrices($price['extraInfo']['value']['totals']['revenueTotal'])),
                "totalFare" => ($total['totalFare'] ?? 0) +  ($addToRawPrice[$legIndex]['totalFare'] = $this->exchangeSellerPrices($price['extraInfo']['value']['totals']['fareTotal']))
            ];
        }
        $newPrice = new stdClass();
        $adultCount = $this->modifiedPassengersTypeCount['adults'] ?? 0;
        $childCount = $this->modifiedPassengersTypeCount['children'] ?? 0;
        $infantCount = $this->modifiedPassengersTypeCount['infants'] ?? 0;
        foreach ($breakDowns as $type => $priceDetails) {
            $passengerType = ['ADT' => 'adult', 'CHD' => 'child', 'INF' => 'infant'][$type];
            $typeCount = ${$passengerType . 'Count'};
            $newPriceBreakDowns[$passengerType]  = [
                'baseFare' => $priceDetails['baseFare'],
                'tax' => $tx = $priceDetails['totalFare'] - $priceDetails['baseFare'],
                'totalFare' => $priceDetails['totalFare'],
                'commission' => 0,
                'payable' => $priceDetails['totalFare'],
                'passengerTypeTotal' => [
                    'baseFare' => $priceDetails['baseFare'] * $typeCount,
                    'tax' => $tx * $typeCount,
                    'totalFare' => $priceDetails['totalFare'] * $typeCount,
                    'commission' => 0,
                    'payable' => $priceDetails['totalFare'] * $typeCount,
                    'passengersCount' => $typeCount
                ]
            ];
        }


        if (!empty($infantCount)) {
            $bf = $this->exchangeSellerPrices($infantPrice['total']);
            $tx = $this->exchangeSellerPrices($infantPrice['taxes']);
            $p = $tx + $bf;
            $newPriceBreakDowns['infant']  = [
                'baseFare' => $bf / $this->queryParams['infants'],
                'tax' =>  $tx / $this->queryParams['infants'],
                'totalFare' =>  $p / $this->queryParams['infants'],
                'commission' => 0,
                'payable' =>  $p / $this->queryParams['infants'],
                'passengerTypeTotal' => [
                    'baseFare' => $bf,
                    'tax' => $tx,
                    'totalFare' => $p,
                    'commission' => 0,
                    'payable' => $p,
                    'passengersCount' => $this->queryParams['infants']
                ]
            ];

            $total['baseFare'] = $total['baseFare'] + $newPriceBreakDowns['infant']['passengerTypeTotal']['baseFare'];
            $total['totalFare'] = $total['totalFare'] + $newPriceBreakDowns['infant']['passengerTypeTotal']['payable'];
        }
        $exchangedBalanceDue = $this->exchangeSellerPrices($balanceDue);
        if (round(abs(round($exchangedBalanceDue, 7) - round($total['totalFare'], 7)), 7) > 0.1) {
            lugError('price mismatch', ['requested' => $this->queryParams, 'result' => $legsPrice, 'infantPrice' => $infantPrice, 'balanceDue' => $balanceDue,  'exchangedBalanceDue' => $exchangedBalanceDue, 'total' => $total, 'breakDowns' => $breakDowns, 'exchangedPriceBreakDowns' => $newPriceBreakDowns]);
            $this->addError('price mismatch');
            return false;
        }

        $newPrice->raw = ['exchangeData' => $this->exchangeData, 'exchanged' => $addToRawPrice, 'legs' => $legsPrice, 'infantPrice' => $infantPrice, 'balanceDue' => $balanceDue,  'exchangedBalanceDue' => $exchangedBalanceDue];
        $newPrice->breakDowns = $newPriceBreakDowns;
        $newPrice->currency = $this->seller->currency;
        $newPrice->baseFare = $total['baseFare'];
        $newPrice->payable = $exchangedBalanceDue; //> there might be 0.1 or less difference between exchangedBalanceDue and $total['totalFare'] bc totalFare is the sum of all exchanged, each rouded up, and exchangedBalanceDue is the rounded up exchanged of BalanceDue
        $newPrice->diff =  $newPrice->payable - $newPrice->baseFare;
        return $newPrice;
    }
}

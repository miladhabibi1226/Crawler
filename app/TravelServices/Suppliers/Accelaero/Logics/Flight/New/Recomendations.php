<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight\New;


trait Recomendations
{

    protected function getRecommendations($rawResults)
    {
        $allFlights = [];
        $routeFlights = [];
        $finalRecommendation = [];
        foreach ($this->translatedQueryParams['legs'] as $translatedQueryParams) {
            $orgDes = $translatedQueryParams['origin']['abb'] . '/' . $translatedQueryParams['destination']['abb'];
            $date = $translatedQueryParams['departure'];
            if (!isset($rawResults[$orgDes]['dateWiseFlightCombinations'][$date])) {
                lugWarning("Date $date is not available for $orgDes", ['rawResults' => $rawResults, 'query params' => $this->translatedQueryParams]);
                continue;
            }
            foreach ($rawResults[$orgDes]['dateWiseFlightCombinations'] as $dateWiseFlightCombination => $dateData) {
                if (!empty($dateData['flightOptions'])) {
                    foreach ($dateData['flightOptions'] as $flightOption) {
                        if ($flightOption['availabilityStatus'] !== 'AVAILABLE') continue;
                        foreach ($flightOption['cabinPrices'] as $cabinPrices) {
                            $allFlights[$orgDes][] = ['segmentInfo' => $flightOption['flightSegments'], 'priceInfo' => $cabinPrices];
                        }
                    }
                }
            }
        }
        if (count($allFlights) !== count($this->translatedQueryParams['legs'])) {
            lugWarning('some routs have empty flights in supplier response ', ['query_params' => $this->translatedQueryParams, 'rawResult' => $rawResults]);
            return;
        }
        foreach ($allFlights as $route => $flights) {
            foreach ($flights as &$flight) {
                foreach ($flight['segmentInfo'] as &$segment) {
                    $segment['cabinPrices'] = $flight['priceInfo'];
                }
                unset($segment);
            }
            unset($flight);
            $routeFlights[] = $flights;
        }
        $combinations = $this->getCombinations($routeFlights);
        foreach ($combinations as $index => $combination) {
            $legs = [];
            $prices = [];
            foreach ($combination as $flight) {
                $legs[] = $flight['segmentInfo'];
                $prices[] = $flight['priceInfo'];
            }
            $finalRecommendation[$index] = [
                'legs' => $legs,
                'priceInfo' => $prices
            ];
        }
        return $finalRecommendation;
    }

    protected function getCombinations($routeFlights)
    {
        $result = [[]];
        foreach ($routeFlights as $index => $flights) {
            $temp = [];
            foreach ($result as $resultItem) {
                foreach ($flights as $key => $flight) {
                    $temp[] = array_merge($resultItem, [$flight]);
                }
            }
            $result = $temp;
        }
        return $result;
    }
}

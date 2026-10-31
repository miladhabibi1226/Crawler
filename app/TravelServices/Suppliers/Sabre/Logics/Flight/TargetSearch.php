<?php

namespace App\TravelServices\Suppliers\Sabre\Logics\Flight;

use App\TravelServices\Logics\Flight\TargetSearch as CommonTargetSearch;

trait TargetSearch
{
    use CommonTargetSearch, CustomSearch {
        CommonTargetSearch::priceInfo insteadof CustomSearch;
        CommonTargetSearch::chekCapacity insteadof CustomSearch;
    }

    protected function filterWeight($serviceInfo) ///> re writing this here bc in search we only have adt baggage but in custom search we have them all. but adt's should be the same
    {
        foreach ($serviceInfo['legs'] as $legIndex => $leg) {
            $resultLeg = $leg['segments'][0];
            $bufferWeight = $this->queryParams['legs'][$legIndex]['flightInfo'];
            preg_match_all('/(\d+) (\w+)\/\w{3}/', $resultLeg['luggage'], $output_array);
            $resultWeight = $output_array[0];
            foreach ($resultWeight as $passengerTypeWeight) {
                if (!str_contains($bufferWeight['luggage'], $passengerTypeWeight)) {
                    return false;
                }
            }
            return true;
        }
    }
}

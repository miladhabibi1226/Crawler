<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics\Flight;

use App\TravelServices\Logics\Flight\CustomSearch as CommonCustomSearch;

trait CustomSearch
{
    use Search, CommonCustomSearch {
        CommonCustomSearch::itinerary insteadof Search;
        CommonCustomSearch::done insteadof Search;
        CommonCustomSearch::failed insteadof Search;
    }

    protected function filterWeight($serviceInfo) ///> re writing this here bc in search we only have adt baggage but in custom search we have them all. but adt's should be the same
    {
        foreach ($serviceInfo['legs'] as $legIndex => $leg) {
            $resultLeg = $leg['segments'][0];
            $bufferWeight = $this->queryParams['legs'][$legIndex]['flightInfo'];
            if ($resultLeg['luggage'] !== ($bufferWeight['luggage'] ?? $resultLeg['luggage'])) {
                foreach ($this->passengersTypeCount as $passengerType => $count) {
                    $type = ['adults' => 'ADT', 'children' => 'CHD', 'infants' => 'INF'][$passengerType];
                    if ($count > 0) {
                        preg_match("/(([\d\w]+)\/$type)/", $resultLeg['luggage'], $passengerBagResult);
                        preg_match("/(([\d\w]+)\/$type)/", $bufferWeight['luggage'] ?? $resultLeg['luggage'], $passengerBagBuffer);
                        // lugWarning(__FUNCTION__, [$resultLeg, $passengerBagResult, $bufferWeight, $passengerBagBuffer]);
                        if (($passengerBagResult[0] ?? '-') != $passengerBagBuffer[0]) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }
}

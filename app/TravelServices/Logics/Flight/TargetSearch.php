<?php

namespace App\TravelServices\Logics\Flight;

trait TargetSearch
{
    protected function priceInfo($price) /// rewritten for accelaero
    {
        $data = $this->calculatePriceforEachItem([['price_info' => json_encode($price)]]);
        return json_decode($data[0]['price_info'] ?? '', 'true');
    }

    protected function chekCapacity($flight)
    {
        ///> since with modified passenger count we search for diff psngrfrm than the requested, we need to check if the flight has capacity. some suppliers don't gice us the capacity
        foreach ($flight['service']['legs'] as  $leg) {
            if ($leg['info']['capacity'] !== null && $leg['info']['capacity'] !== '-' && $leg['info']['capacity'] < $this->passengersCount) {
                lugWarning(__METHOD__, [$leg, $this->passengersCount]);
                return false;
            }
        }
        return true;
    }
}

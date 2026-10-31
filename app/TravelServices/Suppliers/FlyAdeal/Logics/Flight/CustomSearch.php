<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics\Flight;

use App\TravelServices\Logics\Flight\CustomSearch as CommonCustomSearch;

trait CustomSearch
{
    use Search, CommonCustomSearch {
        CommonCustomSearch::itinerary insteadof Search;
        CommonCustomSearch::done insteadof Search;
        CommonCustomSearch::failed insteadof Search;
    }

    protected function getPriceQuote($recoomendations)
    {
        ///> for fluadeal, the price that we receive in get availability is not the final price. it does not includes infant price and some additional taxes that only get added in priceQuote api
        ///> so we'll call priceQuote to get the final price that
        $this->multi = true;
        $auth = $this->seller->api->configs;
        $this->call = [];
        ///> priceQuotes is for each journyKey/fareKey combination
        ///> since we might have multiple recomendation for each journyKey/fareKey combination, and we want to avoide calling the same request for all recomendation, we need to make sure we call priceQuote once for each combination
        foreach ($recoomendations as $recIndex => $recoomendation) {
            ///> making sure we'll only call for the flight that we want to check
            foreach ($recoomendation['legs'] as $legIndex => $legInfo) {
                $flightNumber = $rbdc = [];
                foreach ($legInfo['segments'] as $segment) {
                    $flightNumber[] = $segment['identifier']['identifier'];
                    $rbdc[] = "{$segment['details']['extraInfo']['classOfService']}-{$segment['bundleInfo']['bundleCode']}";
                }
                $flightNumber = implode('/', $flightNumber);
                $rbdc = implode('/', $rbdc);
                $ogFlight = $this->queryParams['legs'][$legIndex]['flightInfo'];
                if ($ogFlight['flightNumber'] != $flightNumber || $ogFlight['class'] != $rbdc) {
                    unset($recoomendations[$recIndex]);
                    continue 2;
                }
            }
            $keys = $this->combineHourneyKeys($recoomendation);
            if (empty($this->call[$keys])) {
                $depends = $recoomendation + $auth;
                $this->call[$keys] = [
                    'fields' => json_encode($this->getBCFields($depends)),
                    'url' =>  $this->cleanUrlSlash($auth['url']) . "/api/nsk/v2/bookings/quote",
                    'apiMethod' => 'BookingQuote'
                ];
            }
        }
        $this->action();
        return $this->addPriceQuoteTorecomendations($recoomendations ?? []);
    }
}

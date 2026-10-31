<?php

namespace App\TravelServices\Suppliers\Nira\Logics\Flight;

use App\TravelServices\Logics\Flight\CustomSearch as CommonCustomSearch;

trait CustomSearch
{
    use Search, CommonCustomSearch {
        CommonCustomSearch::itinerary insteadof Search;
        CommonCustomSearch::done insteadof Search;
        CommonCustomSearch::failed insteadof Search;
    }

    public function canBeDone() {}
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
            if ($RDB !=  $this->queryParams['legs'][0]['flightInfo']['class']) continue;
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
}

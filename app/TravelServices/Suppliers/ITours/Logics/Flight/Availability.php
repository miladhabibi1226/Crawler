<?php

namespace App\TravelServices\Suppliers\ITours\Logics\Flight;

use App\TravelServices\Logics\Flight\Search as FlightSearch;

trait Availability
{
    use FlightSearch;
    protected $latestSession;

    protected function canBeDone()
    {
        if (!$this->authorizeRequest()) {
            return $this->addError('Can not get session');
        }
    }

    public function requestGenerator()
    {
        $queryParams = $this->translateQueryParams();
        $depends = $this->seller->api->configs + $queryParams;
        lugInfo("ITours Search Data ({$this->seller->alias})", [$depends, request('modifiedPassengersTypeCount')]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/Partners/Flight/V2/Availability',
            'timeout' => 5,
            'apiMethod' => 'Availability'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->items = ['key' => $this->rawResult['decoded']['result']['key']];
        ///> this key is only valid for 5 minutes. we use it immediately anyway
        // lugInfo('ITours Availability extractor Items', ['items' => $this->items]);
    }

    protected function getFields($depends)
    {
        ///> request('modifiedPassengersTypeCount') eas prepared in search class bc it only happens there
        $fields = [
            "adult" => (int) request('modifiedPassengersTypeCount')['adults'],
            "child" => (int) request('modifiedPassengersTypeCount')['children'],
            "infant" => (int) request('modifiedPassengersTypeCount')['infants'],
            "cabinClassCode" => $depends['cabin'],
            "airTripType" => $this->isOneWaySearch ? 'OneWay' : 'RoundTrip',
            "nonStop" => false
        ];
        foreach ($depends['legs'] as $legIndex => $leg) {
            $fields["departureDates"][] = $leg['departure'];
            $fields["originCodes"][] = $leg['origin']['abb'];
            $fields["destinationCodes"][] = $leg['destination']['abb'];
        }
        return $fields;
    }

    public function done()
    {
        return ['status' => true, 'key' => $this->items['key']];
    }
}

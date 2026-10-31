<?php

namespace App\TravelServices\Suppliers\Quantum\Logics\Flight;

use App\TravelServices\Logics\Flight\TargetSearch as CommonTargetSearch;

trait TargetSearch
{
    use CommonTargetSearch, CustomSearch {
        CommonTargetSearch::priceInfo insteadof CustomSearch;
        CommonTargetSearch::chekCapacity insteadof CustomSearch;
    }

    protected function regularRequestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = ['requestType' => env('API_INTERNAL', true) ? 'internal' : 'internalWithData', 'bufferReferenceId' => $this->object->bufferReferenceId];
        // lugInfo('Blade Data', [$depends]);
        $this->call = [
            'api' => 'v0/targetSearch',
            'host' => $auth['url'],
            'variables' => [$this->travelService],
            'fields' => $depends,
            'apiMethod' => 'targetSearch',
            'httpMethod' => 'POST'
        ];
    }

    protected function filterWeight($serviceInfo)
    {
        ///>  Weight is diff for diff suppliers. already checked in og node
        return true;
    }
}

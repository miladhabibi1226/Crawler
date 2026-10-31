<?php

namespace App\TravelServices\Suppliers\BasisFly\Logics;

use Carbon\Carbon;

use App\TravelServices\Logics\Common as LogicsCommon;

trait Common
{
    use LogicsCommon, Errors;
    protected $sessionLifeTime;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $auth = $this->seller->api->configs;
        $this->supplierName = 'BasisFly';
        $this->defaultHeader = ['Content-Type:application/json'];
        $this->dataReference = types('supplierDataReference', 'BasisFly');
        $this->sessionLifeTime = 20;
    }

    protected function haveSupplierResponseError()
    {
        if (($this->rawResult['decoded']['Status'] ?? true) != true) {
            $rawError =  $this->rawResult['decoded']['ErrorMessage'];
            lugWSResError("BasisFly response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError["Message"] ?? $rawError);
            return $rawError["Message"] ?? $rawError;
        }
        if ($this->rawResult['http_code'] != 200) {
            lugWSResError("BasisFly response is invalid ({$this->seller->alias})", ['rawResult' => $this->rawResult]);
            $this->supplierConversaion('invalid response status: ' . $this->rawResult['http_code']);
            return 'BasisFly response is invalid';
        }
        $this->supplierConversaion();
        return false;
    }

    protected function prepareFlightGroup()
    {
        $counter = 1;
        foreach ($this->getServiceInfo()['legs'] as $leg) {
            $routesInfo = [];
            foreach ($leg['segments'] as $key => $segment) {
                $routesInfo[] = [
                    "FlightNumber" => $segment['flight_number'],
                    "AirlineCode" => $segment['airline']['abb'],
                    "DepartureDate" => Carbon::createFromFormat('Y-m-d\TH:i', $segment['departure']['raw_time'])->toDateString(),
                    "DepartureTime" => $segment['departure']['time'],
                    "ArrivalDate" =>  Carbon::createFromFormat('Y-m-d\TH:i', $segment['arrival']['raw_time'])->toDateString(),
                    "ArrivalTime" => $segment['arrival']['time'],
                    "OriginAirport" => $segment['departure']['airport']['abb'],
                    "DestinationAirport" => $segment['arrival']['airport']['abb'],
                    "Class" => $segment['cabin'],
                    "SegmentId" => $counter
                ];
                $counter++;
            }
            $flightGroup[] = ["RoutesInfo" => $routesInfo];
        }
        return $flightGroup;
    }
}

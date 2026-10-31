<?php

namespace App\TravelServices\Suppliers\Nira;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\Nira\Logics\Common;
use Carbon\Carbon;

class Ping extends Remote
{
    use Common, LogicsPing;

    public function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Nira/FlightIssue.json'));
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }

        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs;
        lugInfo('Ping Data', [$depends]);
        $DepartureDate = Carbon::now()->format('Y-m-d');
        $query = http_build_query([
            "cbSource" => "ugt",
            "cbTarget" => "ttq",
            "cbAdultQty" => 1,
            "cbChildQty" => 0,
            "cbInfantQty" => 0,
            "OfficeUser" => $depends['officeUser'],
            "OfficePass" => $depends['officePass'],
            "DepartureDate" => $DepartureDate,
            "Airline" => $this->Airline
        ]);
        $this->call = [
            'fields' => "",
            'url' => "{$depends['url']}/ws1/AvailabilityJS.jsp?{$query}",
            'method' => "GET",
            'timeout' => 10,
            'apiMethod' => 'Search'
        ];
        return $this->action();
    }

    protected function haveSupplierResponseError()
    {
        $rawError = !empty($this->rawResult['decoded']) ? true : false;
        if (!$rawError) {
            lugWSResError("Nira response contains error ({$this->seller->alias})", ['supplierError: ' => $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError ?? $rawError);
            return $this->rawResult['decoded']['Message'] ?? 'Error Happen';
        }
        $this->supplierConversaion();
        return false;
    }
}

<?php

namespace App\TravelServices\Suppliers\Faranegar;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\Faranegar\Logics\Common;

class Ping extends Remote
{
    use Common, LogicsPing;

    protected function getContent()
    {
        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
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
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'method' => 'POST',
            'url' =>  $depends['url'] . '/Air_Available'
        ];
        return $this->action();
    }

    protected function haveSupplierResponseError()
    {
        $rawError = $this->rawResult['decoded']['Success'] ?? false;
        if (!$rawError) {
            lugWSResError("Faranegar response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $this->rawResult, 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($rawError ?? $rawError);
            return $this->rawResult['decoded']['Message'] ?? 'Error Happen';
        }
        $this->supplierConversaion();
        return false;
    }

    protected function getFields($depends)
    {
        return [
            "POS" => [
                "UserName" => $depends['username'],
                "Password" => $depends['password']
            ],
            "OriginDestinationInformation" => [
                [
                    "DepartureDateTime" => "2023-05-22 00:00",
                    "OriginLocation" => ["LocationCode" => "TBZ"],
                    "DestinationLocation" => ["LocationCode" => "THR"]
                ]
            ],
            "TravelerInfoSummary" => [
                "AirTravelerAvail" => [
                    [
                        "Code" => "ADT",
                        "Quantity" => 1
                    ],
                    [
                        "Code" => "CHD",
                        "Quantity" => 0
                    ],
                    [
                        "Code" => "INF",
                        "Quantity" => 0
                    ]
                ]
            ],
            "Cabin" => "Y"
        ];
    }
}

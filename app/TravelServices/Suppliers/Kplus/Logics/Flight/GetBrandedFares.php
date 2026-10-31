<?php

namespace App\TravelServices\Suppliers\Kplus\Logics\Flight;

trait GetBrandedFares
{
    use BeforeBook;
    protected $responseSample = 'Samples/Kplus/GetBrandedFares.json';
    protected $method = 'GetBrandedFares';
    protected function getFields($depends)
    {
        return [
            "request" => [
                "FareAlternativeLegKeys" => $depends['searchKeys'],
                "Token" => ["TokenCode" => $this->latestSession]
            ]
        ];
    }
}

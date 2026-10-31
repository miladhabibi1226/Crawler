<?php

namespace App\TravelServices\Suppliers\Kplus\Logics\Flight;

trait Revalidate
{
    use BeforeBook;
    protected $responseSample = 'Samples/Kplus/Validate.json';
    protected $method = 'Validate';
    protected function getFields($depends)
    {
        return [
            "request" => [
                "Air" => ["FareAlternativeLegKeys" => $depends['GetBrandedFaresKeys']],
                "Token" => ["TokenCode" => $this->latestSession]
            ]
        ];
    }
}

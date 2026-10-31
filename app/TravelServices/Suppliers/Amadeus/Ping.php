<?php

namespace App\TravelServices\Suppliers\Amadeus;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\Amadeus\Logics\Common;
use Carbon\Carbon;

class Ping extends Remote
{
    use Common, LogicsPing;
    protected $method = 'Fare_MasterPricerTravelBoardSearch';

    protected function getContent()
    {
        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        $xml = new \SimpleXMLElement($this->rawResult['body']);
        $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('//soap:Body')), true)[0] ?? [];
        $error = $this->haveSupplierResponseError();
        if (in_array($error, [' 12|Presentation|soap message header incorrect', ' 11|Session|'])) {
            $this->addError('Information incorrect');
            return false;
        }
    }

    public function requestGenerator()
    {
        $depends = $this->headerData() + ['include' => 'Ping', 'date' => Carbon::now()->addMonth()->format('dmy')];
        lugInfo('Blade Data', [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.stateless', $depends)->render(),
            'url' =>  $depends['api_url'],
            'apiMethod' => 'Ping'
        ];
        return $this->action();
    }
}

<?php

namespace App\TravelServices\Suppliers\BasisFly\Logics\Flight;

use App\Models\SupplierSession;
use Carbon\Carbon;

trait SignIn
{
    protected $latestSession;
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
        $auth = $this->seller->api->configs;
        $this->call = [
            'fields' => json_encode(["ApiKey" => $auth['apiKey']]),
            'url' => $this->cleanUrlSlash($auth['url']) . "/api-test/Flight_v2/CreateSession",
            'timeout' => 5,
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->items = [['session' => $this->rawResult['decoded']['SessionId']]];
    }

    public function mapper()
    {
        $this->latestSession = $this->rawResult['decoded']['SessionId'] ?? $this->supplierSession->session ?? false;
        $this->supplierSession = SupplierSession::create([
            'session' =>  $this->latestSession ?? $this->callException('empty SessionId'),
            'expire_on' => Carbon::now()->addMinutes($this->sessionLifeTime),
            'seller_id' => $this->seller->id,
            'searcher_identity' => 'Fake_Searcher_Identity',
        ]);
    }

    public function done()
    {
        return ['status' => true, 'supplierSession' => $this->supplierSession];
    }
}

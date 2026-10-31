<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics\Flight;

use App\Models\SupplierSession;
use Carbon\Carbon;

trait SignIn
{
    protected $latestSession;
    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/$this->supplierName/FlightSignIn.json"));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Request generator unsuccessful");
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
        $auth = $this->seller->api->configs;
        $this->call = [
            'fields' => json_encode(["Password" => $auth['password'], "AccountNumber" =>  $auth['accountNumber'], "UserName" => $auth['username']]),
            'url' => $this->cleanUrlSlash($auth['url']) . "/api/CreateSession",
            'timeout' => 5,
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        if (empty($this->rawResult['decoded']['Data']['SessionId'])) {
            $this->addError('can not get session');
        }
    }

    public function mapper()
    {
        $this->latestSession = $this->rawResult['decoded']['Data']['SessionId'];
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

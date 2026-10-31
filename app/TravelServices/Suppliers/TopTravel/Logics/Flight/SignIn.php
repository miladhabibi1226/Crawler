<?php

namespace App\TravelServices\Suppliers\TopTravel\Logics\Flight;

use App\Models\SupplierSession;
use Carbon\Carbon;

trait SignIn
{
    protected $latestSession;
    protected function getContent()
    {
        // $this->fromFile = 1;
         if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/TopTravel/FlightSignIn.json'));
            $this->rawResult['http_code'] = 200;
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
        $auth = $this->seller->api->configs;
        $this->call = [
            'fields' => $this->getFields($auth),
            'url' => $this->cleanUrlSlash($auth['url']) . "/satguruapiout/oauth/token",
            'headers' => ["Authorization: Basic " . base64_encode($auth['auth_username'] . ':' . $auth['auth_password']), 'Cookie: ROUTEID=.node3'],
            'cookie' => 'Cookie: ROUTEID=.node3',
            'timeout' => 5,
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function getFields($auth)
    {
        return [
            "username" => $auth['username'],
            "password" => $auth['password'],
            "grant_type" => $auth['grant_type']
        ];
    }

    protected function extractor()
    {
        if (empty($this->rawResult['decoded']['access_token'])) {
            $this->lugError('empty access_token ', [$this->rawResult]);
            $this->addError('response is invalid');
            return;
        }
        $this->items = [['session' => $this->rawResult['decoded']['access_token']]];
    }

    public function mapper()
    {
        $this->latestSession = $this->rawResult['decoded']['access_token'] ?? $this->supplierSession->session ?? false;
        $this->supplierSession = SupplierSession::create([
            'session' =>  $this->latestSession,
            'expire_on' => Carbon::now()->addSeconds($this->rawResult['decoded']['expires_in']),
            'seller_id' => $this->seller->id,
            'searcher_identity' => 'Fake_Searcher_Identity',
        ]);
    }
    public function done()
    {
        return ['status' => true, 'supplierSession' => $this->supplierSession];
    }
}

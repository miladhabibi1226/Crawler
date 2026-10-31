<?php

namespace App\TravelServices\Suppliers\FlyErbil\Logics\Flight;

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
        $depends = $this->seller->api->configs;
        lugInfo('SignIn Data', [$depends]);
        $data = [
            'username' => $depends['username'],
            'password' => $depends['password'],
            'client_secret' => $depends['clientSecret'],
            'client_id' => $depends['clientId'],
            'grant_type' => 'password'
        ];
        $this->call = [
            'fields' => http_build_query($data),
            'url' =>  $depends['authenticationUrl'] . '/protocol/openid-connect/token',
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->items = ['accessToken' => $this->rawResult['decoded']['access_token'], 'refreshToken' => $this->rawResult['decoded']['refresh_token']];
        lugInfo('FlyErbil Signin extractor items', [$this->items]);
    }

    public function mapper()
    {
        $this->latestSession = $this->items['decoded']['access_token'] ?? false;
        $this->supplierSession = SupplierSession::create([
            'session' =>  createReferenceId(),
            'expire_on' => Carbon::now()->addMinutes($this->sessionLifeTime),
            'seller_id' => $this->seller->id,
            'extras' => json_encode($this->items),
            'searcher_identity' => 'Fake_Searcher_Identity',
        ]);

        lugInfo('New Supplier Session Created', [$this->supplierSession]);
    }

    public function done()
    {
        return ['status' => true, 'supplierSession' => $this->supplierSession];
    }
}

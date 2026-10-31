<?php

namespace App\TravelServices\Suppliers\CityNet\Logics\Flight;

use App\Models\SupplierSession;
use Carbon\Carbon;

trait SignIn
{

    protected function getContent()
    {
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/CityNet/SignIn.json'));
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
        lugInfo('Signin data', $depends);
        $fields = [
            "username" => $depends['username'],
            "password" => $depends['password']
        ];
        $headers = [
            "origin: {$depends['origin']}",
            'Content-Type: application/json'
        ];
        $this->call = [
            'fields' => json_encode($fields),
            'url' => $this->cleanUrlSlash($depends['url']) . '/authenticate',
            'headers' => $headers,
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->latestSession = $this->rawResult['decoded']['token'] ?? false;
        if (empty($this->latestSession)) {
            $this->addError("Empty accessToken");
        }
        $this->items = ['session' => $this->latestSession];

        lugInfo('CityNet SignIn extractor Items', ['items' => $this->items]);
    }

    public function mapper()
    {
        $this->supplierSession = SupplierSession::create([
            'session' =>  createReferenceId(),
            'expire_on' => Carbon::now()->addMinutes($this->sessionLifeTime),
            'seller_id' => $this->seller->id,
            'extras' => $this->items,
            'searcher_identity' => 'Fake_Searcher_Identity',
        ]);
    }

    public function done()
    {
        return ['status' => true, 'supplierSession' => $this->supplierSession];
    }
}

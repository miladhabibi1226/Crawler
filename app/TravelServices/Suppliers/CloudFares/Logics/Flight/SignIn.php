<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

use App\Models\SupplierSession;
use Carbon\Carbon;

trait SignIn
{
    protected $latestSession;

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/CloudFares/SignIn.json'));
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
            'fields' => json_encode([
                "type" => "login",
                "email" => $auth['username'],
                "password" => $auth['password']
            ]),
            'url' => $this->cleanUrlSlash($auth['url']) . "/api/b2b/v1/login",
            'timeout' => 10,
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        if (empty($this->rawResult['decoded']['data']['token'])) {
            return $this->addError('token is empty');
        }
        $this->items = ['session' => $this->rawResult['decoded']['data']['token'], 'ExpiresAt' => $this->rawResult['decoded']['data']['expire']];
    }

    public function mapper()
    {
        $this->latestSession = $this->items['session'] ?? false;
        $this->supplierSession = SupplierSession::create([
            'session' => $this->latestSession,
            'expire_on' => Carbon::now()->addMinutes(15),
            'seller_id' => $this->seller->id,
            'searcher_identity' => 'Fake_Searcher_Identity'
        ]);
    }

    public function done()
    {
        return ['status' => true, 'supplierSession' => $this->supplierSession];
    }
}

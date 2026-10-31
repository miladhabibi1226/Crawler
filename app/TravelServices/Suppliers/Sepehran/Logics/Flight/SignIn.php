<?php

namespace App\TravelServices\Suppliers\Sepehran\Logics\Flight;

use App\Models\SupplierSession;
use Carbon\Carbon;


trait SignIn
{
    protected $latestSession;
    protected function getContent()
    {
        $this->fromFile=false;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Sepehran/SignIn.json'));
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
            'fields' => '',
            'url' => $this->cleanUrlSlash($auth['url']) . "/api/v2/accounting/getAuthorizeToken",
            'headers' =>["api-username:{$auth['username']}", "api-password:{$auth['password']}", 'Language:en'],
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }
    protected function extractor()
    {
        if (empty($this->rawResult['decoded']['data']['authorization'])) {
            return $this->addError('token is empty');
        }
        $this->items = ['session' => $this->rawResult['decoded']['data']['authorization'], 'ExpiresAt' => $this->rawResult['decoded']['data']['expired_at']];
    }

    public function mapper()
    {
        $this->latestSession = $this->rawResult['decoded']['data']['authorization'] ?? $this->supplierSession->session ?? false;
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

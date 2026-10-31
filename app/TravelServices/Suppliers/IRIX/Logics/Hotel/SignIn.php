<?php

namespace App\TravelServices\Suppliers\IRIX\Logics\Hotel;

use App\Models\SupplierSession;
use Carbon\Carbon;

trait SignIn
{
    protected $latestSession;

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        lugInfo('SignIn data', [$auth]);
        $clientId = $auth['clientId'];
        $clientSecret = $auth['clientSecret'];
        $this->call = [
            'fields' => json_encode(["grant_type" => "client_credentials", "scope" => "read:mapping read:hotels-search write:hotels-book"]),
            'url' => $this->cleanUrlSlash($auth['url']) . '/oauth2/token',
            'headers' => ["Authorization: Basic " . base64_encode("$clientId:$clientSecret")],
            'timeout' => 5,
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->latestSession = $this->rawResult['decoded']['access_token'] ?? false;
        if (empty($this->latestSession)) {
            $this->addError("Empty accessToken");
        }
        $this->items = ['supplierSession' => $this->latestSession];
        // lugInfo('S>>>> IRIX Hotel SignIn Extractor items - ' . $this->seller->alias, ['items' => $this->items]);
    }

    public function mapper()
    {
        $this->supplierSession = SupplierSession::create([
            'session' =>  createReferenceId(),
            'expire_on' => Carbon::now()->addSeconds($this->rawResult['decoded']['expires_in']),
            'seller_id' => $this->seller->id,
            'extras' => $this->items,
            'searcher_identity' => 'Fake_Searcher_Identity',
        ]);
    }

    public function done()
    {
        return ['status' => true, 'supplierSession' => $this->supplierSession, 'items' => $this->items];
    }
}

<?php

namespace App\TravelServices\Suppliers\Sabre\Logics\Flight;

use App\Models\SupplierSession;
use Carbon\Carbon;

trait SignIn
{
    public function requestGenerator()
    {
        $this->defaultHeader = []; ///> emptying this to avoid confilict
        lugInfo('SignIn data', [$this->seller->api->configs]);
        if (request('sessionType', 'rest') != 'rest') {
            return $this->xmlRequestGenerator();
        }
        $auth = $this->seller->api->configs;
        $Authorization = base64_encode(base64_encode("V1:{$auth['epr']}:{$auth['pcc']}:AA") . ':' . base64_encode($auth['password']));
        $this->call = [
            'url' => $this->cleanUrlSlash($auth['url']) . "/v2/auth/token",
            'headers' => [
                "grant_type:client_credentials",
                "Content-Type:application/x-www-form-urlencoded",
                "Authorization:Basic $Authorization"
            ],
            'timeout' => 10,
            'fields' => '',
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    public function xmlRequestGenerator()
    {
        $auth = $this->seller->api->configs;
        $this->call = [
            'url' => $auth['soapUrl'],
            'headers' => [
                "Content-Type:text/xml",
            ],
            'timeout' => 10,
            'fields' => $this->minifyXML(view('Sabre.SignIn', $auth)->render()),
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->latestSession();
        if (empty($this->latestSession)) {
            $this->addError("Empty accessToken");
        }
        $this->items = ['session' => $this->latestSession];
        lugInfo('Sabre SignIn extractor Items', ['items' => $this->items]);
    }

    public function mapper()
    {
        $this->supplierSession = SupplierSession::create([
            'session' =>  $this->latestSession,
            'expire_on' => Carbon::now()->addMinutes(request('sessionType', 'rest') === 'rest' ? $this->sessionLifeTime : 15),
            'seller_id' => $this->seller->id,
            'searcher_identity' => request('sessionType', 'rest'),
        ]);
    }

    protected function latestSession()
    {
        if (request('sessionType', 'rest') === 'rest') {
            $this->latestSession = $this->rawResult['decoded']['access_token'] ?? false;
        } else {
            $this->latestSession = $this->rawResult['decoded']['Security']['BinarySecurityToken'] ?? false;
        }
    }

    protected function decodeRawResult()
    {
        if (request('sessionType', 'rest') === 'rest') {
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
        } else {
            $xml = preg_replace('/wsse:/m', '', $this->rawResult['body']);
            $xml = new \SimpleXMLElement($xml);
            $this->rawResult['decoded'] = json_decode(json_encode($xml->xpath('soap-env:Header')), true)[0] ?? [];
        }
    }

    public function done()
    {
        return ['status' => true, 'supplierSession' => $this->supplierSession];
    }
}

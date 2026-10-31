<?php

namespace App\TravelServices\Suppliers\Kplus\Logics\Flight;

use App\Models\SupplierSession;
use Carbon\Carbon;

trait SignIn
{
    protected $latestSession;

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Kplus/SignIn.json'));
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
                "channelCredential" => [
                    "ChannelCode" => $auth['username'],
                    "ChannelPassword" => $auth['password']
                ]
            ]),
            'url' => $this->cleanUrlSlash($auth['url']) . "/General.svc/Rest/Json/CreateTokenV2",
            'timeout' => 10,
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->items = ['session' => $this->rawResult['decoded']['Result']['TokenCode'], 'ExpiresAt' => $this->rawResult['decoded']['Result']['ExpiresAt']];
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

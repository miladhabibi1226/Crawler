<?php

namespace App\TravelServices\Suppliers\ITours\Logics\Flight;

use App\Models\SupplierSession;
use Carbon\Carbon;

trait SignIn
{
    protected function getContent()
    {
        if ($this->fromFile) {
            lugWarning('From Flie');
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/ITours/SignIn.json'));
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $this->latestSession = 'fromFile';
            return true;
        }

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
        lugInfo('Signin data', $depends);
        $fields = [
            "apiKey" => $depends['apiKey'],
            "tenantId" => $depends['tenantId'],
            "username" => $depends['username'],
            "password" => $depends['password'],
            "currency" => $this->seller->currency->abb
        ];
        $this->call = [
            'fields' => json_encode($fields),
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/Partners/Flight/V2/Authenticate',
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->latestSession = $this->rawResult['decoded']['result']['accessToken'] ?? false;
        if (empty($this->latestSession)) {
            $this->addError("Empty accessToken");
        }
        $this->items = ['session' => $this->latestSession];

        lugInfo('ITours SignIn extractor Items', ['items' => $this->items]);
    }

    public function mapper()
    {
        $this->supplierSession = SupplierSession::create([
            'session' =>  createReferenceId(),
            'expire_on' => Carbon::now()->addSeconds($this->rawResult['decoded']['result']['expireInSeconds']),
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

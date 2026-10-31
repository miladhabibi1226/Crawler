<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics\Flight;

use App\Models\SupplierSession;
use Carbon\Carbon;

trait SignIn
{
    protected function getContent()
    {
        if ($this->fromFile) {
            lugWarning('From Flie');
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/Navitaire/SignIn.json'));
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
            "credentials" => [
                "userName" => $depends['username'],
                "password" => $depends['password'],
                "domain" => 'WW2',
                "channelType" => 'API',
            ],
            "applicationName" => 'Luxota'

        ];
        $this->call = [
            'fields' => json_encode($fields),
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/jz/v1/Token',
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        $this->latestSession = $this->rawResult['decoded']['data']['token'] ?? false;
        if (empty($this->latestSession)) {
            $this->addError("Empty accessToken");
        }
        $this->items = ['session' => $this->latestSession, 'idleTimeoutInMinutes' => $this->rawResult['decoded']['data']['idleTimeoutInMinutes'] ?? false];
        // lugInfo('ITours Navitaire extractor Items', ['items' => $this->items]);
    }

    public function mapper()
    {
        $this->supplierSession = SupplierSession::create([
            'session' =>  $this->items['session'],
            'expire_on' => Carbon::now()->addMinutes(15),
            'seller_id' => $this->seller->id,
            'searcher_identity' => 'Fake_Searcher_Identity',
        ]);
    }

    public function done()
    {
        return ['status' => true, 'supplierSession' => $this->supplierSession];
    }
}

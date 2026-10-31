<?php

namespace App\TravelServices\Suppliers\Accelaero\Logics\Flight\New;

use App\Models\SupplierSession;
use Carbon\Carbon;

trait SignIn
{
    protected $latestSession;

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/$this->supplierName/AirArabia/FlightSignIn.json"));
            $this->rawResult['http_code'] = 200;
        } else {

            if ($this->requestGenerator() === false) {
                $this->addError("Request generator unsuccessful");
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
            'fields' => json_encode(["login" =>  'ABY' . $auth['username'], "password" => $auth['password']]),
            'url' => $this->cleanUrlSlash($auth['tokenUrl']),
            'headers' => ['Content-Type:application/json'],
            'timeout' => 5,
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }

    protected function extractor()
    {
        if (empty($this->rawResult['decoded']['tokenPair']['accessToken'])) {
            $this->addError('empty session result ');
        }
    }

    public function mapper()
    {
        $this->latestSession = $this->rawResult['decoded']['tokenPair']['accessToken'];
        $this->supplierSession = SupplierSession::create([
            'session' =>  'AirArabiaSignIn',
            'expire_on' => Carbon::now()->addMinutes($this->sessionLifeTime),
            'seller_id' => $this->seller->id,
            'extras' => $this->latestSession ?? $this->callException('empty SessionId'),
            'searcher_identity' => 'Fake_Searcher_Identity',
        ]);
    }

    public function done()
    {
        return ['status' => true, 'supplierSession' => $this->supplierSession];
    }

}

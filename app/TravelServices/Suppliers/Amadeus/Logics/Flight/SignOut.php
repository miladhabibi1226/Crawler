<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

use Carbon\Carbon;

trait SignOut
{
    protected $method = 'SingOut';
    protected function getContent()
    {
        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }
    }

    public function requestGenerator()
    {
        foreach ($this->object as $object) {
            $depends = $this->headerData() + ['session' => json_decode($object->extras, true)];
            if ($object->created_at > Carbon::now()->subMinutes(15)) continue;
            $this->call[] = [
                'fields' => view('Amadeus.SignOut', $depends)->render(),
                'url' => $this->cleanUrlSlash($depends['api_url']),
                'apiMethod' => 'SignOut'
            ];
        }
        $this->multi = true;
        return $this->action();
    }

    protected function done()
    {
        foreach ($this->object as $supplierSession) {
            if ($supplierSession->created_at > Carbon::now()->subMinutes(15)) continue;
            $supplierSession->delete();
        }
        return ['status' => true];
    }
}

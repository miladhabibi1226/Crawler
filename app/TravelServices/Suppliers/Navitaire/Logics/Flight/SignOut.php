<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics\Flight;

use App\TravelServices\Logics\SignOut as LogicsSignOut;
use Carbon\Carbon;

trait SignOut
{
    use LogicsSignOut;
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
            $this->call[] = [
                'fields' => '',
                'url' => $this->cleanUrlSlash($this->seller->api->configs['url']) . $this->getSignOutUrl(),
                'headers' => ["Authorization:{$object->session}"],
                'method' => 'DELETE',
                'apiMethod' => 'SignOut'
            ];
        }
        $this->multi = true;
        return $this->action();
    }

    protected function getSignOutUrl()
    {
        return '/api/jz/v1/Token';
    }
}

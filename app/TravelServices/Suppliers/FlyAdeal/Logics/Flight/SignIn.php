<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics\Flight;

use App\TravelServices\Suppliers\Navitaire\Logics\Flight\SignIn as NavitaireSignIn;

trait SignIn
{
    use NavitaireSignIn;
    public function requestGenerator()
    {
        $depends = $this->seller->api->configs;
        $fields = [
            "applicationName" => "",
            "credentials" => [
                "userName" => $depends['username'],
                "password" => $depends['password'],
                "domain" => "EXT"
            ],
        ];
        $this->call = [
            'fields' => json_encode($fields),
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/nsk/v2/token',
            'apiMethod' => 'SignIn'
        ];
        return $this->action();
    }
}

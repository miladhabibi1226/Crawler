<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics\Flight;

use App\TravelServices\Suppliers\Navitaire\Logics\Flight\SignOut as NavitaireSignOut;

trait SignOut
{
    use NavitaireSignOut;
    protected function getSignOutUrl()
    {
        return '/api/nsk/v1/token';
    }
}

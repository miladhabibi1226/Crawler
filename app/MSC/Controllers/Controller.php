<?php

namespace App\MSC\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    public function __construct()
    {
        $this->middleware(\MatinUtils\NodeTracker\SocketEngine\Middleware::class);
    }
}

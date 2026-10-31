<?php

namespace App\MSC\Protocol;

use MatinUtils\EasySocket\Protocols\Logics\Base;
use MatinUtils\EasySocket\Protocols\Logics\Request as LogicsRequest;
use MatinUtils\EasySocket\Protocols\Logics\Response as LogicsResponse;
use MatinUtils\EasySocket\Protocols\Logics\Router as LogicsRouter;

class MSC extends Base
{
    public function __construct($socket = null) {
        parent::__construct($socket);
        $this->continuous = true;
    }

    public function getRouter(): LogicsRouter
    {
        return new Router;
    }

    public function getRequest($raw): LogicsRequest
    {
        return new Request($raw);
    }

    public function getResponse(): LogicsResponse
    {
        return new Response();
    }
}

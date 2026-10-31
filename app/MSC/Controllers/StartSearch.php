<?php

namespace App\MSC\Controllers;

use App\TravelServices\Handler;

class StartSearch extends Controller
{
    public function index($request, $response)
    {
        $sellerid = $request->variables(3);
        $sessionId = $request->variables(4);
        // lugInfo("Crawler start sellerid $sellerid", [$request->variables()]);
        app('translator')->setLocale($request->variables(6));
        Handler::Search($sellerid, $sessionId);
    }
}

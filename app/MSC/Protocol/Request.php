<?php

namespace App\MSC\Protocol;

use MatinUtils\EasySocket\Protocols\Logics\Request as LogicsRequest;

class Request extends LogicsRequest
{
    protected $variables, $input;
    public function __construct($raw)
    {
        $raw = app('easy-socket')->cleanData($raw);
        parent::__construct($raw);
        $this->input = $this->raw;
        $this->variables = explode(' ', $this->input);
        app('db')->reconnect(); //> bc db sometimes reads the records from cache
        $this->mergeRequest();
    }

    public function input()
    {
        return $this->input;
    }

    public function getNodeId()
    {
        return $this->variables[2] ?? null;
    }

    public function getPid()
    {
        return $this->variables[5] ?? uniqid();
    }

    public function variables($key = null)
    {
        if (empty($key)) {
            return $this->variables;
        }
        return $this->variables[$key] ?? null;
    }

    protected function mergeRequest()
    {
        //> bc we use the request() data outside Controller
        $this->renewSingleton();
    }

    protected function renewSingleton()
    {
        ///> to clear all the data in request() from the prev task
        app()->singleton('request', function ($app) {
            return new \Illuminate\Http\Request();
        });
    }
}

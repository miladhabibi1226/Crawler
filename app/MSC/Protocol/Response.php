<?php

namespace App\MSC\Protocol;

use Illuminate\Support\Arr;
use MatinUtils\EasySocket\Protocols\Logics\Response as LogicsResponse;

class Response implements LogicsResponse
{
    protected $items = [];
    public $exception;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function closeConnection(): bool
    {
        return false;
    }

    public function returnable(): bool
    {
        return false;
    }

    public function getOutput(): string
    {
        if (!isset($this->items['status'])) {
            $this->setKey('status', null);
        }
        if (!empty($this->exception)) {
            lugError($this->exception->getMessage(), ['request()->all()' => request()->all(), 'exceptionLine' => $this->exception->getFile() . ' line: ' . $this->exception->getLine(),  'trace' => $this->exception->getTraceAsString()]);
            $this->setKey('status', false);
            $this->setKey('pid', app('log-system')->getPid());

            if ($this->exception instanceof \Illuminate\Validation\ValidationException) {
                $this->setKey('code', 422);
                $this->setKey('message', $this->exception->getMessage());
                $this->setKey('errors', $this->exception->errors() ?? []);
            }
        }
        return json_encode($this->items);
    }

    public function getKey($key, $default = null)
    {
        return  Arr::get($this->items, $key, $default);
    }

    public function setKey($key, $value = null)
    {
        return  Arr::set($this->items, $key, $value);
    }

    public function set($data = [])
    {
        $this->items = array_merge($this->items ?? [], $data);
        return  $this->items;
    }

    public function status()
    {
        return ($this->items['status'] ?? 'false') == true ? 200 : 470;
    }
}

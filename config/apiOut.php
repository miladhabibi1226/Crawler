<?php

return [
    'sendType' => env('API_SEND_TYPE', 'http'),
    'easySocket' => [
        'host' => env('API_SOCKET_HOST', '127.0.0.1'),
        'port' => env('API_SOCKET_PORT', 0),
    ]
];

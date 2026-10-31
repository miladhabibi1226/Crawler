<?php

return [
    'filePath' => env('SOCKET_FILE_PATH', 'bootstrap/easySocket'),
    'host' => env('SOCKET_HOST', '127.0.0.1'),
    'port' => env('SOCKET_PORT', 0),
    'maxClientNumber' => env('MAX_SOCKET_CLIENTS', 50),
    'interval' => null,

    'defaultProtocol' => 'MSC',
    'protocols' => [
        'MSC' => 'App\MSC\Protocol',
    ],
    'middlewares' => [
        'MatinUtils\NodeTracker\SocketMiddleware',
    ]
];

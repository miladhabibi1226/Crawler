<?php

return [
    'host' => env('NOTIFICATION_HOST', 'http://notification.api'),
    'sendType' => env('NOTIFICATION_SEND_TYPE', 'http'),
    'easySocket' => [
        'host' => env('NOTIFICATION_SOCKET_HOST', '127.0.0.1'),
        'port' => env('NOTIFICATION_SOCKET_PORT', 0),
    ]
];

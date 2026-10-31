<?php

return [
    'sendType' => env('LOG_SEND_TYPE', 'http'),
    'errorReporting' => env('LOG_ERROR_REPORT', false),
    'excludes' => [
        "Illuminate\Validation\ValidationException",
        "Symfony\Component\HttpKernel\Exception\NotFoundHttpException",
        "Illuminate\Auth\AuthenticationException",
    ],
    'activeTypes' => env("LOG_ACTIVE_TYPES", "info,dd,dump,warning,error,debug,WSResError"),
    'easySocket' => [
        'host' => env('LOG_SOCKET_HOST', '127.0.0.1'),
        'port' => env('LOG_SOCKET_PORT', 5000)
    ]
];

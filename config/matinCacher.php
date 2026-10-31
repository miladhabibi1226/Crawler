<?php

return [
    'clusters' => [
        'tables' => [
            'port' => env('CACHER_CONNECTION_TABLES_PORT', env('MATIN_CACHER_SOCKET_PORT', 0)),
            'host' => env('CACHER_CONNECTION_TABLES_HOST', env('MATIN_CACHER_SOCKET_HOST', ''))
        ],
        'localInventory' => [
            'port' => env('CACHER_CONNECTION_INVENTORY_PORT', env('MATIN_CACHER_SOCKET_PORT', 0)),
            'host' => env('CACHER_CONNECTION_INVENTORY_HOST', env('MATIN_CACHER_SOCKET_HOST', ''))
        ],
        'popularRoutes' => [
            'port' => env('CACHER_CONNECTION_POPULAR_ROUTES_PORT', env('MATIN_CACHER_SOCKET_PORT', 0)),
            'host' => env('CACHER_CONNECTION_POPULAR_ROUTES_HOST', env('MATIN_CACHER_SOCKET_HOST', ''))
        ],
        'default' => [
            'port' => env('CACHER_CONNECTION_DEFAULT_PORT', env('MATIN_CACHER_SOCKET_PORT', 0)),
            'host' => env('CACHER_CONNECTION_DEFAULT_HOST', env('MATIN_CACHER_SOCKET_HOST', ''))
        ],
        'supplier' => [
            'port' => env('CACHER_CONNECTION_SUPPLIER_PORT', env('MATIN_CACHER_SOCKET_PORT', 0)),
            'host' => env('CACHER_CONNECTION_SUPPLIER_HOST', env('MATIN_CACHER_SOCKET_HOST', ''))
        ],
        'subscriptions' => [
            'port' => env('CACHER_CONNECTION_SUBSCRIPTIONS_PORT', env('MATIN_CACHER_SOCKET_PORT', 0)),
            'host' => env('CACHER_CONNECTION_SUBSCRIPTIONS_HOST', env('MATIN_CACHER_SOCKET_HOST', ''))
        ]
    ]
];

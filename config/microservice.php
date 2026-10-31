<?php

return [
    'commands' => [
        'update' => [
            'git pull'
        ],
        'upgrade' => [
            'composer update',
            'git pull'
        ],
        'test' => [
            'vendor/bin/phpunit --stop-on-failure --stop-on-error'
        ]
    ]
];

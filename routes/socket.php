<?php

return [
    'ping' => 'Validations@ping',

    'search' => 'Search@index',
    'aftersearch' => 'Search@after',
    'search/local' => 'Search@local',
    'search/custom' => 'Search@custom',
    'targetSearch' => 'Search@targetSearch',

    'validate/passengers' => ['uses' => 'Validations@passengersInfo', 'as' => 'passengers-validation'],

    'beforebook' => 'BookAndIssue@before',
    'book' => ['uses' => 'BookAndIssue@book', 'as' => 'book-only'],
    'bookAndIssue' => ['uses' => 'BookAndIssue@index', 'as' => 'bookAndIssue'],
    'pay' => ['uses' => 'Payment@index', 'as' => 'pay'],
    'bookingData' => ['uses' => 'BookAndIssue@data', 'as' => 'booking-data'],
];

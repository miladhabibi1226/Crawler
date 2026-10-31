<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/


$router->get('/', function () {
    return response('Unauthorized', 401);
});

$router->group(['prefix' => '{nodeId}', 'middleware' => ['nodeTracker']], function ($router) {
    $router->get('ping/{sellerId}', 'Validations@ping'); /// make a controller if it needs one

    ///     Search
    $router->post('search/local/{travelService}',  'Search@local');
    $router->post('search/custom',  'Search@custom');
    $router->post('targetSearch',  'Search@targetSearch');

    $router->get('search/{sessionId}',  'Search@index');
    $router->get('aftersearch/{travelService}/{referenceId}/{action}', 'Search@after');

    $router->post('changeBook/{bookingReferenceId}/{action}',  'BookAndIssue@changeBook');
    $router->get('changeBook/{bookingReferenceId}/{action}',  'BookAndIssue@changeBook');

    ///     Passengers
    $router->group(['middleware' => 'auditLog'], function ($router) {
        $router->get('validate/passengers/{referenceId}', ['as' => 'passengers-validation-get', 'uses' => 'Validations@passengersInfo']);
        $router->post('validate/passengers/{referenceId}', ['as' => 'passengers-validation', 'uses' => 'Validations@passengersInfo']);
    });

    ///     Booking
    $router->group(['middleware' => 'auditLog'], function ($router) {
        $router->get('bookAndIssue/{referenceId}',  ['as' => 'bookAndIssue', 'uses' => 'BookAndIssue@index']);
        $router->get('book/{referenceId}',  ['as' => 'book-only', 'uses' => 'BookAndIssue@book']);
        $router->get('issue/{referenceId}',  ['as' => 'issue', 'uses' => 'BookAndIssue@issue']);
    });
    $router->addRoute(['GET', 'POST'], 'beforebook/{travelService}/{referenceId}/{action}', 'BookAndIssue@before');
    $router->addRoute(['GET', 'POST'], 'afterbook/{travelService}/{referenceId}/{action}', ['as' => 'after-book', 'uses' =>  'BookAndIssue@after']);
    $router->addRoute(['GET', 'POST'], 'bookingData/{referenceId}', ['as' => 'bookingData', 'uses' =>  'BookAndIssue@data']);


    ///     Payment
    $router->group(['middleware' => 'auditLog'], function ($router) {
        $router->get('pay/{referenceId}', ['as' => 'pay', 'uses' =>  'Payment@pay']);
    });

    $router->get('test', function () {
        dd($_SERVER);
    });
});









$router->post('web', function () {

    $delay = $_POST['delay'];

    $pid = getmypid();
    $t = microtime('true');
    echo "delay is: $delay - $pid  - $t\n\n";
    sleep($delay);
    $m = memory_get_usage();
    $mb = $m / (1024 * 1024);
    $t = microtime('true');
    echo "end:  $pid - $t - Ram: $mb - \n\n";
});

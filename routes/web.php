<?php

/** @var \Laravel\Lumen\Routing\Router $router */

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

$router->options('/auth-token', [
    'middleware' => [App\Http\Middleware\CorsAllowUnconditional::class],
    fn() => new Illuminate\Http\Response(),
]);
$router->options('/rpc', [
    'middleware' => [App\Http\Middleware\CorsAllowUnconditional::class],
    fn() => new Illuminate\Http\Response(),
]);
$router->post('/auth-token', 'TokenController@mintToken');
$router->post('/rpc', 'RpcController@handleCall');

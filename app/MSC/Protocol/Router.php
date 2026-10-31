<?php

namespace App\MSC\Protocol;

use MatinUtils\EasySocket\Protocols\Logics\Request;
use MatinUtils\EasySocket\Protocols\Logics\Response;
use MatinUtils\EasySocket\Protocols\Logics\Router as LogicsRouter;

class Router implements LogicsRouter
{
    protected $routes;

    public function handle(Request $request, Response &$response)
    {
        $class = "App\MSC\Controllers\StartSearch";
        $controller = new $class;
        $function = 'index';
        $methodMiddlewares = $controller->getMiddlewareForMethod($function);

        $next = function ($request, &$response) use ($controller, $function) {
            try {
                $controller->$function($request, $response);
            } catch (\Throwable $th) {
                $response->exception = $th;
            }
        };
        for ($i = count($methodMiddlewares) - 1; $i >= 0; $i--) {
            $middlewareClass = $methodMiddlewares[$i];
            $next = function ($request, &$response) use ($middlewareClass, $next) {
                try {
                    $middleware = new $middlewareClass;
                    $middleware->handle($request, $response, $next);
                } catch (\Throwable $th) {
                    $response->exception = $th;
                }
            };
        }

        try {
            $next($request, $response);
        } catch (\Throwable $th) {
            $response->exception = $th;
        }
        if (!empty($response->exception)) {
            $forLug=['request()->all()' => request()->all(), 'exceptionLine' => $response->exception->getFile() . ' line: ' . $response->exception->getLine(),  'trace' => $response->exception->getTraceAsString()];
            if ($response->exception instanceof \Illuminate\Validation\ValidationException) {
                $forLug['errors'] = $response->exception->errors($forLug);
            }
            app('log')->info("Error happened. pid: " . app('log-system')->getPID());
            app('log')->info($response->exception->getMessage());
            lugError($response->exception->getMessage(), $forLug);
        }
    }
}

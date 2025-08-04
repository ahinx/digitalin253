<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))


    // ->withBootstrap(function () {
    //     foreach (glob(__DIR__ . '/../app/Helpers/*.php') as $file) {
    //         require_once $file;
    //     }
    // })


    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',

    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();


// // ✅ Muat semua helper di app/Helpers/
// foreach (glob(__DIR__ . '/../app/Helpers/*.php') as $file) {
//     require_once $file;
// }

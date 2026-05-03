<?php

use App\Http\Middleware\CustomThrottleResponse;
use App\Http\Middleware\ValidateDeviceAndTokens;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
		then: function () {
            Route::middleware('api')
                ->prefix('payments')
                ->name('payments.')
                ->group(__DIR__.'/../app/Modules/Payment/routes/payments.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
		$middleware->validateCsrfTokens(except: [
			'api/*'
		]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        
    })->create();

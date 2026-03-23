<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Rutas de la PWA de marcado remoto
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/checkin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('checkin*')) {
                return route('checkin.login');
            }
            return route('filament.admin.auth.login');
        });
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureCompanySelected::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

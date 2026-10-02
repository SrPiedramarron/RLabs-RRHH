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
$middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('checkin*')) {
                return route('checkin.login');
            }
            return route('filament.admin.auth.login');
        });
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureCompanySelected::class);
        $middleware->validateCsrfTokens(except: [
            'iclock/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sesión/token CSRF vencido (ej. dejó la app abierta horas con la
        // pantalla bloqueada): en vez de la pantalla fría "419 Page Expired",
        // lo regresamos al login de donde vino con un aviso claro — pedido
        // recurrente, oct. 2026.
        // Laravel convierte TokenMismatchException en HttpException(419)
        // ANTES de llegar a los render(); por eso se captura por código de
        // estado. Se deja pasar a Livewire/JSON, que manejan su propio 419.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson() || $request->hasHeader('X-Livewire')) {
                return null;
            }

            $login = $request->is('checkin*') ? route('checkin.login') : route('filament.admin.auth.login');

            return redirect($login)->with('error', 'Tu sesión expiró o la conexión se cortó. Vuelve a intentar.');
        });
    })->create();

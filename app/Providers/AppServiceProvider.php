<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
	if (config('app.env') === 'production') {
        URL::forceScheme('https');
    }
// Superadmin tiene acceso a todo
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });

        Mail::extend('brevo', fn () => new BrevoApiTransport(config('services.brevo.api_key')));
    }
}
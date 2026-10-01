<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    // "Recuérdame" marcado por defecto: minimiza cuántas veces RRHH tiene
    // que volver a loguearse cuando la sesión de 24h expira por inactividad
    // (oct. 2026, mismo pedido que en el login de checkin).
    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label(__('filament-panels::pages/auth/login.form.remember.label'))
            ->default(true);
    }
}

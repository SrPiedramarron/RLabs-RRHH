<?php

use App\Http\Controllers\Checkin\AuthController;
use App\Http\Controllers\Checkin\CheckinController;
use Illuminate\Support\Facades\Route;

// ── Rutas públicas (sin autenticación) ───────────────────────────────────────
Route::prefix('checkin')->name('checkin.')->group(function () {

    // Redirigir raíz a login
    Route::get('/', fn() => redirect()->route('checkin.login'));

    // Login
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

    // Manifest y service worker (PWA)
    Route::get('/manifest.json', [CheckinController::class, 'manifest'])->name('manifest');
    Route::get('/sw.js',         [CheckinController::class, 'serviceWorker'])->name('sw');

    // ── Rutas protegidas ──────────────────────────────────────────────────────
    Route::middleware('auth:employee')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Cambiar contraseña
        Route::get('/cambiar-clave',  [AuthController::class, 'showCambiarClave'])->name('cambiar-clave');
        Route::post('/cambiar-clave', [AuthController::class, 'cambiarClave'])->name('cambiar-clave.submit');

        // Pantalla principal — marcar entrada/salida
        Route::get('/home',  [CheckinController::class, 'home'])->name('home');
        Route::post('/home', [CheckinController::class, 'marcar'])->name('marcar');

        // Historial de checkins remotos
        Route::get('/historial', [CheckinController::class, 'historial'])->name('historial');

        // Mis marcaciones del mes
        Route::get('/asistencia', [CheckinController::class, 'asistencia'])->name('asistencia');

    });
});

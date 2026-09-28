<?php

use App\Http\Controllers\Checkin\AuthController;
use App\Http\Controllers\Checkin\CheckinController;
use App\Http\Controllers\Checkin\SolicitudController;
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

        // Mis solicitudes: vacaciones, permisos, corrección de horas
        Route::get('/solicitudes',        [SolicitudController::class, 'index'])->name('solicitudes.index');
        Route::get('/solicitudes/nueva',  [SolicitudController::class, 'create'])->name('solicitudes.create');
        Route::post('/solicitudes',       [SolicitudController::class, 'store'])->name('solicitudes.store');

        Route::get('/solicitudes/nueva/permiso',  [SolicitudController::class, 'createPermiso'])->name('solicitudes.permiso.create');
        Route::post('/solicitudes/permiso',       [SolicitudController::class, 'storePermiso'])->name('solicitudes.permiso.store');

        Route::get('/solicitudes/nueva/correccion', [SolicitudController::class, 'createCorreccion'])->name('solicitudes.correccion.create');
        Route::post('/solicitudes/correccion',      [SolicitudController::class, 'storeCorreccion'])->name('solicitudes.correccion.store');

    });
});

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a7f4b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Asistencia">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'AsistenciaRLabs')</title>

    <link rel="manifest" href="/checkin/manifest.json">
    <link rel="apple-touch-icon" href="/images/checkin/icon-192.png">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --verde:       #1a7f4b;
            --verde-dark:  #145f38;
            --verde-light: #e8f5ee;
            --rojo:        #dc3545;
            --amarillo:    #ffc107;
            --gris:        #6c757d;
            --gris-light:  #f8f9fa;
            --texto:       #212529;
            --borde:       #dee2e6;
            --radio:       12px;
            --sombra:      0 2px 12px rgba(0,0,0,0.08);
        }

        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--gris-light);
            color: var(--texto);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Layout ── */
        .pwa-wrapper { display: flex; flex-direction: column; min-height: 100vh; }

        .pwa-header {
            background: var(--verde);
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .pwa-header h1 { font-size: 17px; font-weight: 600; }
        .pwa-header .subtitle { font-size: 12px; opacity: 0.85; margin-top: 1px; }

        .pwa-content { flex: 1; padding: 20px 16px; max-width: 480px; margin: 0 auto; width: 100%; }

        .pwa-nav {
            background: white;
            border-top: 1px solid var(--borde);
            display: flex;
            position: sticky;
            bottom: 0;
            z-index: 100;
        }
        .pwa-nav a {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 4px;
            text-decoration: none;
            color: var(--gris);
            font-size: 11px;
            gap: 4px;
            transition: color .2s;
        }
        .pwa-nav a.active, .pwa-nav a:hover { color: var(--verde); }
        .pwa-nav a svg { width: 22px; height: 22px; }

        /* ── Cards ── */
        .card {
            background: white;
            border-radius: var(--radio);
            padding: 20px;
            box-shadow: var(--sombra);
            margin-bottom: 16px;
        }
        .card-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--gris);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 12px;
        }

        /* ── Botones ── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: var(--radio);
            font-size: 16px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            width: 100%;
            transition: opacity .2s, transform .1s;
        }
        .btn:active { transform: scale(0.98); }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn-verde   { background: var(--verde);   color: white; }
        .btn-rojo    { background: var(--rojo);    color: white; }
        .btn-gris    { background: var(--gris);    color: white; }
        .btn-outline { background: transparent; border: 2px solid var(--verde); color: var(--verde); }

        /* ── Formularios ── */
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #444; }
        .form-input {
            width: 100%;
            padding: 13px 14px;
            border: 1.5px solid var(--borde);
            border-radius: 10px;
            font-size: 16px;
            outline: none;
            transition: border-color .2s;
            background: white;
        }
        .form-input:focus { border-color: var(--verde); }
        .form-error { color: var(--rojo); font-size: 12px; margin-top: 5px; }

        /* ── Alertas ── */
        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 16px;
            font-weight: 500;
        }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #1a7f4b; }
        .alert-error   { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-verde    { background: #d4edda; color: #155724; }
        .badge-rojo     { background: #f8d7da; color: #721c24; }
        .badge-amarillo { background: #fff3cd; color: #856404; }
        .badge-gris     { background: #e2e3e5; color: #383d41; }
        .badge-azul     { background: #cce5ff; color: #004085; }

        /* ── Spinner ── */
        .spinner {
            width: 20px; height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading .spinner { display: block; }
        .loading .btn-text { display: none; }
    </style>

    @stack('styles')
</head>
<body>
<div class="pwa-wrapper">

    {{-- Header --}}
    <header class="pwa-header">
        <div>
            <h1>@yield('header-title', 'AsistenciaRLabs')</h1>
            @hasSection('header-subtitle')
            <div class="subtitle">@yield('header-subtitle')</div>
            @endif
        </div>
        @yield('header-right')
    </header>

    {{-- Contenido --}}
    <main class="pwa-content">

        {{-- Alertas flash --}}
        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        @yield('content')
    </main>

    {{-- Navegación inferior (solo para páginas autenticadas) --}}
    @auth('employee')
    <nav class="pwa-nav">
        <a href="{{ route('checkin.home') }}" class="{{ request()->routeIs('checkin.home') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Marcar
        </a>
        <a href="{{ route('checkin.historial') }}" class="{{ request()->routeIs('checkin.historial') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Remotos
        </a>
        <a href="{{ route('checkin.asistencia') }}" class="{{ request()->routeIs('checkin.asistencia') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Asistencia
        </a>
        <a href="{{ route('checkin.cambiar-clave') }}" class="{{ request()->routeIs('checkin.cambiar-clave') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            Cuenta
        </a>
    </nav>
    @endauth

</div>
@stack('scripts')
</body>
</html>

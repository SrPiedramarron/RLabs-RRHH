<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1E3A5F">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="RLabsRRHH">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'RLabsRRHH')</title>

    <link rel="manifest" href="/checkin/manifest.json">
    <link rel="apple-touch-icon" href="/images/checkin/icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@500&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --azul:        #2563EB;
            --azul-dark:   #1E3A5F;
            --azul-mid:    #1d4ed8;
            --azul-light:  #EFF6FF;
            --azul-border: #BFDBFE;
            --rojo:        #EF4444;
            --amarillo:    #F59E0B;
            --verde:       #10B981;
            --gris:        #6B7280;
            --gris-light:  #F3F4F6;
            --gris-border: #E5E7EB;
            --texto:       #111827;
            --radio:       14px;
            --sombra:      0 1px 3px rgba(0,0,0,0.08), 0 4px 16px rgba(37,99,235,0.06);
            --sombra-lg:   0 4px 6px rgba(0,0,0,0.05), 0 10px 40px rgba(37,99,235,0.1);
        }

        html, body {
            height: 100%;
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F0F4FF;
            color: var(--texto);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Layout ── */
        .pwa-wrapper { display: flex; flex-direction: column; min-height: 100vh; }

        .pwa-header {
            background: linear-gradient(135deg, #1E3A5F 0%, #2563EB 100%);
            color: white;
            padding: 16px 20px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 20px rgba(37,99,235,0.3);
        }

        .pwa-header-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pwa-header-icon {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
        }

        .pwa-header h1 {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .pwa-header .subtitle {
            font-size: 11px;
            opacity: 0.75;
            margin-top: 1px;
            font-weight: 400;
        }

        .pwa-content {
            flex: 1;
            padding: 20px 16px;
            max-width: 480px;
            margin: 0 auto;
            width: 100%;
        }

        .pwa-nav {
            background: white;
            border-top: 1px solid var(--gris-border);
            display: flex;
            position: sticky;
            bottom: 0;
            z-index: 100;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.06);
        }
        .pwa-nav a {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 4px 8px;
            text-decoration: none;
            color: var(--gris);
            font-size: 10px;
            font-weight: 500;
            gap: 3px;
            transition: color .2s;
            position: relative;
        }
        .pwa-nav a.active {
            color: var(--azul);
        }
        .pwa-nav a.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 20%;
            right: 20%;
            height: 2px;
            background: var(--azul);
            border-radius: 0 0 4px 4px;
        }
        .pwa-nav a svg { width: 22px; height: 22px; }

        /* ── Cards ── */
        .card {
            background: white;
            border-radius: var(--radio);
            padding: 20px;
            box-shadow: var(--sombra);
            margin-bottom: 14px;
            border: 1px solid rgba(37,99,235,0.06);
        }
        .card-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--gris);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
        }

        /* ── Botones ── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: var(--radio);
            font-size: 15px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            border: none;
            cursor: pointer;
            width: 100%;
            transition: opacity .2s, transform .1s, box-shadow .2s;
            letter-spacing: -0.2px;
        }
        .btn:active { transform: scale(0.98); }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .btn-verde   { background: linear-gradient(135deg, #059669, #10B981); color: white; box-shadow: 0 4px 14px rgba(16,185,129,0.3); }
        .btn-rojo    { background: linear-gradient(135deg, #DC2626, #EF4444); color: white; box-shadow: 0 4px 14px rgba(239,68,68,0.3); }
        .btn-azul    { background: linear-gradient(135deg, #1d4ed8, #2563EB); color: white; box-shadow: 0 4px 14px rgba(37,99,235,0.3); }
        .btn-gris    { background: var(--gris-light); color: var(--texto); }
        .btn-outline { background: transparent; border: 2px solid var(--azul); color: var(--azul); }

        /* Alias para compatibilidad */
        .btn-verde-legacy { background: linear-gradient(135deg, #059669, #10B981); color: white; box-shadow: 0 4px 14px rgba(16,185,129,0.3); }

        /* ── Formularios ── */
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #374151; }
        .form-input {
            width: 100%;
            padding: 13px 14px;
            border: 1.5px solid var(--gris-border);
            border-radius: 10px;
            font-size: 16px;
            font-family: 'DM Sans', sans-serif;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            background: white;
            color: var(--texto);
        }
        .form-input:focus {
            border-color: var(--azul);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        .form-error { color: var(--rojo); font-size: 12px; margin-top: 5px; }

        /* ── Alertas ── */
        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 16px;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-success { background: #ECFDF5; color: #065F46; border-left: 4px solid #10B981; }
        .alert-error   { background: #FEF2F2; color: #7F1D1D; border-left: 4px solid #EF4444; }
        .alert-warning { background: #FFFBEB; color: #78350F; border-left: 4px solid #F59E0B; }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .badge-verde    { background: #D1FAE5; color: #065F46; }
        .badge-rojo     { background: #FEE2E2; color: #7F1D1D; }
        .badge-amarillo { background: #FEF3C7; color: #78350F; }
        .badge-gris     { background: #F3F4F6; color: #374151; }
        .badge-azul     { background: #DBEAFE; color: #1E40AF; }

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

        /* ── Fade in ── */
        .pwa-content { animation: fadeUp .3s ease; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>

    @stack('styles')
</head>
<body>
<div class="pwa-wrapper">

    {{-- Header --}}
    <header class="pwa-header">
        <div class="pwa-header-logo">
            <div class="pwa-header-icon">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                    <circle cx="12" cy="12" r="9"/>
                    <path stroke-linecap="round" d="M12 7v5l3 3"/>
                </svg>
            </div>
            <div>
                <h1>@yield('header-title', 'RLabsRRHH')</h1>
                @hasSection('header-subtitle')
                <div class="subtitle">@yield('header-subtitle')</div>
                @endif
            </div>
        </div>
        @yield('header-right')
    </header>

    {{-- Contenido --}}
    <main class="pwa-content">

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

    {{-- Navegación inferior --}}
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

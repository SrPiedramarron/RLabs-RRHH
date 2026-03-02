@extends('checkin.layout')

@section('title', 'Ingresar — AsistenciaRLabs')
@section('header-title', 'AsistenciaRLabs')
@section('header-subtitle', 'Control de Asistencia')

@section('content')

<div style="text-align:center; padding: 24px 0 20px;">
    <div style="width:72px; height:72px; background:var(--verde); border-radius:50%;
                display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
        <svg width="36" height="36" fill="none" stroke="white" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </div>
    <h2 style="font-size:20px; font-weight:700; color:var(--verde);">Bienvenido</h2>
    <p style="font-size:13px; color:var(--gris); margin-top:4px;">Ingresa tus datos para marcar asistencia</p>
</div>

<div class="card">
    <form method="POST" action="{{ route('checkin.login.submit') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="dni">DNI</label>
            <input
                type="number"
                id="dni"
                name="dni"
                class="form-input"
                placeholder="12345678"
                maxlength="8"
                inputmode="numeric"
                value="{{ old('dni') }}"
                autofocus
                required
            >
            @error('dni')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Contraseña</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-input"
                placeholder="••••••"
                required
            >
            @error('password')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-verde" style="margin-top:8px;">
            Ingresar
        </button>

    </form>
</div>

<p style="text-align:center; font-size:12px; color:var(--gris); margin-top:8px;">
    ¿Problemas para ingresar? Contacta a RR.HH.
</p>

@endsection

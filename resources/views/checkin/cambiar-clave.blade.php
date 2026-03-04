@extends('checkin.layout')

@section('title', 'Cambiar Contraseña')
@section('header-title', 'Mi Cuenta')
@section('header-subtitle', auth('employee')->user()->employee->nombre_completo_normal ?? '')

@section('content')

<div class="card">
    <div class="card-title">Cambiar Contraseña</div>

    <form method="POST" action="{{ route('checkin.cambiar-clave.submit') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="password_actual">Contraseña actual</label>
            <input type="password" id="password_actual" name="password_actual"
                   class="form-input" placeholder="••••••" required>
            @error('password_actual')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password_nueva">Nueva contraseña</label>
            <input type="password" id="password_nueva" name="password_nueva"
                   class="form-input" placeholder="Mínimo 6 caracteres" minlength="6" required>
            @error('password_nueva')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password_nueva_confirmation">Confirmar nueva contraseña</label>
            <input type="password" id="password_nueva_confirmation" name="password_nueva_confirmation"
                   class="form-input" placeholder="Repite la contraseña" required>
        </div>

        <button type="submit" class="btn btn-verde">
            Cambiar contraseña
        </button>
    </form>
</div>

{{-- Cerrar sesión --}}
<div class="card" style="margin-top:8px;">
    <div class="card-title">Sesión</div>
    <form method="POST" action="{{ route('checkin.logout') }}">
        @csrf
        <button type="submit" class="btn btn-gris">
            Cerrar sesión
        </button>
    </form>
</div>

@endsection

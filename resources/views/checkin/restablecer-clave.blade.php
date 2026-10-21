@extends('checkin.layout')

@section('title', 'Nueva contraseña')
@section('header-title', 'RLabsRRHH')
@section('header-subtitle', 'Nueva contraseña')

@section('content')

<div class="card">
    <div class="card-title">Ingresa el código</div>
    <p style="font-size:13px; color:var(--gris); margin-bottom:14px;">
        Si tu DNI tiene un correo registrado, te enviamos un código de 6 dígitos. Vence en 15 minutos.
    </p>

    <form method="POST" action="{{ route('checkin.restablecer.submit') }}">
        @csrf
        @if(session('reset_dni'))
        <input type="hidden" name="dni" value="{{ session('reset_dni') }}">
        @else
        <div class="form-group">
            <label class="form-label" for="dni">DNI</label>
            <input type="number" id="dni" name="dni" class="form-input" inputmode="numeric" value="{{ old('dni') }}" required>
            @error('dni')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        @endif

        <div class="form-group">
            <label class="form-label" for="codigo">Código</label>
            <input type="text" id="codigo" name="codigo" class="form-input" inputmode="numeric" maxlength="6"
                   autocomplete="one-time-code" placeholder="123456" style="letter-spacing:4px;" required autofocus>
            @error('codigo')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password_nueva">Nueva contraseña</label>
            @include('checkin._password-input', ['id' => 'password_nueva', 'name' => 'password_nueva', 'placeholder' => 'Mínimo 6 caracteres', 'extra' => 'minlength="6" autocomplete="new-password"'])
            @error('password_nueva')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password_nueva_confirmation">Confirmar contraseña</label>
            @include('checkin._password-input', ['id' => 'password_nueva_confirmation', 'name' => 'password_nueva_confirmation', 'placeholder' => 'Repite la contraseña', 'extra' => 'autocomplete="new-password"'])
        </div>

        <button type="submit" class="btn btn-verde">Cambiar contraseña</button>
    </form>
</div>

<p style="text-align:center; font-size:12px; color:var(--gris); margin-top:8px;">
    <a href="{{ route('checkin.olvide') }}" style="color:var(--azul);">Pedir otro código</a> ·
    <a href="{{ route('checkin.login') }}" style="color:var(--azul);">Volver a ingresar</a>
</p>

@endsection

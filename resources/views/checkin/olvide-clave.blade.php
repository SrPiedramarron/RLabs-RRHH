@extends('checkin.layout')

@section('title', 'Recuperar contraseña')
@section('header-title', 'RLabsRRHH')
@section('header-subtitle', 'Recuperar contraseña')

@section('content')

<div class="card">
    <div class="card-title">¿Olvidaste tu contraseña?</div>
    <p style="font-size:13px; color:var(--gris); margin-bottom:14px;">
        Ingresa tu DNI y te enviaremos un código de 6 dígitos al correo registrado en tu ficha.
    </p>

    <form method="POST" action="{{ route('checkin.olvide.submit') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="dni">DNI</label>
            <input type="number" id="dni" name="dni" class="form-input" placeholder="12345678"
                   inputmode="numeric" value="{{ old('dni') }}" autofocus required>
            @error('dni')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-verde">Enviarme el código</button>
    </form>
</div>

<p style="text-align:center; font-size:12px; color:var(--gris); margin-top:8px;">
    ¿No tienes correo registrado o no te llega? Contacta a RR.HH.<br>
    <a href="{{ route('checkin.login') }}" style="color:var(--azul);">Volver a ingresar</a>
</p>

@endsection

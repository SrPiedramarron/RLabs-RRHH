@extends('checkin.layout')

@section('title', 'Nueva Solicitud')
@section('header-title', 'Solicitar Vacaciones')
@section('header-subtitle', 'RRHH revisará tu pedido')

@section('content')

<div class="card" style="padding:14px 16px; margin-bottom:16px; background:var(--azul-light); border:1px solid var(--azul-border);">
    <div style="font-size:12px; color:var(--azul-dark);">Vacaciones que puedes tomar</div>
    <div style="font-size:20px; font-weight:800; color:var(--azul);">
        {{ $saldo['saldo_actual'] ?? '—' }} días
    </div>
</div>

<form method="POST" action="{{ route('checkin.solicitudes.store') }}" class="card" style="padding:16px;">
    @csrf

    <label style="font-size:12px; font-weight:600; color:var(--gris); display:block; margin-bottom:6px;">Fecha de inicio</label>
    <input type="date" name="fecha_inicio" class="form-input" value="{{ old('fecha_inicio') }}" required style="margin-bottom:14px;">

    <label style="font-size:12px; font-weight:600; color:var(--gris); display:block; margin-bottom:6px;">Fecha de fin</label>
    <input type="date" name="fecha_fin" class="form-input" value="{{ old('fecha_fin') }}" required style="margin-bottom:14px;">

    <label style="font-size:12px; font-weight:600; color:var(--gris); display:block; margin-bottom:6px;">Motivo (opcional)</label>
    <textarea name="motivo" class="form-input" rows="3" style="margin-bottom:6px; resize:none;">{{ old('motivo') }}</textarea>

    @if($errors->any())
    <div class="alert" style="background:#FEE2E2; color:#7F1D1D; border-left:4px solid var(--rojo); margin-top:10px;">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
    @endif

    <button type="submit" class="btn btn-azul" style="margin-top:14px;">Enviar solicitud</button>
</form>

<a href="{{ route('checkin.solicitudes.index') }}" style="display:block; text-align:center; margin-top:14px; font-size:13px; color:var(--gris); text-decoration:none;">
    ← Volver a mis solicitudes
</a>

@endsection

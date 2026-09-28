@extends('checkin.layout')

@section('title', 'Solicitar Permiso')
@section('header-title', 'Solicitar Permiso')
@section('header-subtitle', 'Por cita médica, trámite u otro motivo')

@section('content')

<form method="POST" action="{{ route('checkin.solicitudes.permiso.store') }}" enctype="multipart/form-data" class="card" style="padding:16px;">
    @csrf

    <label style="font-size:12px; font-weight:600; color:var(--gris); display:block; margin-bottom:6px;">Motivo</label>
    <select name="categoria" class="form-input" required style="margin-bottom:14px;">
        <option value="">Selecciona una opción</option>
        <option value="medico"   {{ old('categoria') === 'medico' ? 'selected' : '' }}>Cita / examen médico</option>
        <option value="personal" {{ old('categoria') === 'personal' ? 'selected' : '' }}>Motivo personal</option>
        <option value="tramite"  {{ old('categoria') === 'tramite' ? 'selected' : '' }}>Trámite / diligencia</option>
        <option value="otro"     {{ old('categoria') === 'otro' ? 'selected' : '' }}>Otro</option>
    </select>

    <label style="font-size:12px; font-weight:600; color:var(--gris); display:block; margin-bottom:6px;">Fecha desde</label>
    <input type="date" name="fecha_inicio" class="form-input" value="{{ old('fecha_inicio') }}" required style="margin-bottom:14px;">

    <label style="font-size:12px; font-weight:600; color:var(--gris); display:block; margin-bottom:6px;">Fecha hasta</label>
    <input type="date" name="fecha_fin" class="form-input" value="{{ old('fecha_fin') }}" required style="margin-bottom:14px;">

    <label style="font-size:12px; font-weight:600; color:var(--gris); display:block; margin-bottom:6px;">Detalle</label>
    <textarea name="motivo" class="form-input" rows="3" style="margin-bottom:14px; resize:none;" required>{{ old('motivo') }}</textarea>

    <label style="font-size:12px; font-weight:600; color:var(--gris); display:block; margin-bottom:6px;">Adjuntar documento (opcional)</label>
    <input type="file" name="adjunto" class="form-input" accept=".pdf,.jpg,.jpeg,.png" style="margin-bottom:6px;">
    <div style="font-size:11px; color:var(--gris); margin-bottom:6px;">PDF o imagen, máx. 5MB.</div>

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

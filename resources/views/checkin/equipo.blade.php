@extends('checkin.layout')

@section('title', 'Mi equipo')
@section('header-title', 'Mi equipo')
@section('header-subtitle', 'Solicitudes por aprobar')

@section('content')

<div class="card" style="padding:12px 14px; font-size:12px; color:var(--gris);">
    Las vacaciones y permisos de tu equipo pasan primero por ti. Si no respondes en {{ rtrim(rtrim(number_format($horasEspera, 1), '0'), '.') }} h, la solicitud pasa sola a RRHH.
</div>

@forelse($pendientes as $s)
<div class="card" style="padding:14px; margin-bottom:10px;">
    <div style="font-size:14px; font-weight:700;">{{ $s->employee->nombre_completo }}</div>
    <div style="font-size:13px; color:var(--azul-dark); margin-top:2px;">{{ $s->tipo_label }}</div>
    <div style="font-size:12px; color:var(--gris); margin-top:4px;">
        @if($s->fecha_inicio)
            {{ $s->fecha_inicio->format('d/m/Y') }}
            @if($s->fecha_fin && !$s->fecha_fin->equalTo($s->fecha_inicio))
                → {{ $s->fecha_fin->format('d/m/Y') }}
            @endif
            · {{ $s->created_at->diffForHumans() }}
        @endif
    </div>
    @if($s->motivo)
        <div style="font-size:12px; margin-top:6px;">{{ $s->motivo }}</div>
    @endif
    @if($s->adjunto_path)
        <a href="{{ asset('storage/' . $s->adjunto_path) }}" target="_blank" style="font-size:11px; color:var(--azul); text-decoration:none;">📎 Ver adjunto</a>
    @endif

    <form method="POST" action="{{ route('checkin.equipo.aprobar', $s) }}" style="margin-top:10px;">
        @csrf
        <input type="text" name="comentario" placeholder="Comentario (opcional)" maxlength="500"
               style="width:100%; padding:10px; border:1px solid #E5E7EB; border-radius:8px; font-size:13px; margin-bottom:8px;">
        <button type="submit" style="width:100%; padding:11px; background:#16A34A; color:#fff; border:0; border-radius:8px; font-weight:700;">Aprobar</button>
    </form>

    <form method="POST" action="{{ route('checkin.equipo.rechazar', $s) }}" style="margin-top:8px;">
        @csrf
        <input type="text" name="comentario" placeholder="Motivo del rechazo (obligatorio)" maxlength="500" required
               style="width:100%; padding:10px; border:1px solid #E5E7EB; border-radius:8px; font-size:13px; margin-bottom:8px;">
        <button type="submit" style="width:100%; padding:11px; background:#fff; color:#DC2626; border:1px solid #DC2626; border-radius:8px; font-weight:700;">Rechazar</button>
    </form>
</div>
@empty
<div class="card" style="text-align:center; padding:40px 20px;">
    <div style="font-size:40px; margin-bottom:12px;">✅</div>
    <div style="font-weight:700; color:var(--gris);">Nada pendiente</div>
    <div style="font-size:13px; color:var(--gris); margin-top:6px;">No tienes solicitudes de tu equipo por aprobar.</div>
</div>
@endforelse

@if($resueltas->isNotEmpty())
<div style="font-size:12px; font-weight:600; color:var(--gris); margin:14px 0 8px;">Resueltas recientemente</div>
@foreach($resueltas as $s)
<div class="card" style="padding:10px 14px; margin-bottom:6px; font-size:12px;">
    <strong>{{ $s->employee->nombre_completo }}</strong> · {{ $s->tipo_label }}
    <span style="float:right; color:var(--gris);">
        {{ $s->estado === 'rechazada' && $s->comentario_jefe ? 'Rechazada' : ($s->escalada_at ? 'Pasó a RRHH sin respuesta' : 'Aprobada por ti') }}
    </span>
</div>
@endforeach
@endif

@endsection

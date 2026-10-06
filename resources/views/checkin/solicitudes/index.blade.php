@extends('checkin.layout')

@section('title', 'Mis Solicitudes')
@section('header-title', 'Mis Solicitudes')
@section('header-subtitle', 'Vacaciones, permisos y correcciones')

@section('content')

<div class="card" style="padding:16px;">
    <div style="margin-bottom:14px;">
        <div style="font-size:12px; color:var(--gris);">Saldo de vacaciones</div>
        <div style="font-size:22px; font-weight:800; color:var(--azul);">
            {{ $saldo['saldo_actual'] ?? '—' }} <span style="font-size:13px; font-weight:600; color:var(--gris);">días</span>
        </div>
    </div>

    <div style="font-size:12px; font-weight:600; color:var(--gris); margin-bottom:8px;">Nueva solicitud</div>
    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:8px;">
        <a href="{{ route('checkin.solicitudes.create') }}" style="text-decoration:none; text-align:center; padding:12px 6px; background:var(--azul-light); border-radius:10px;">
            <div style="font-size:20px;">🏖️</div>
            <div style="font-size:11px; font-weight:600; color:var(--azul-dark); margin-top:4px;">Vacaciones</div>
        </a>
        <a href="{{ route('checkin.solicitudes.permiso.create') }}" style="text-decoration:none; text-align:center; padding:12px 6px; background:var(--azul-light); border-radius:10px;">
            <div style="font-size:20px;">📝</div>
            <div style="font-size:11px; font-weight:600; color:var(--azul-dark); margin-top:4px;">Permiso</div>
        </a>
        <a href="{{ route('checkin.solicitudes.correccion.create') }}" style="text-decoration:none; text-align:center; padding:12px 6px; background:var(--azul-light); border-radius:10px;">
            <div style="font-size:20px;">🕒</div>
            <div style="font-size:11px; font-weight:600; color:var(--azul-dark); margin-top:4px;">Corrección</div>
        </a>
    </div>
</div>

@forelse($solicitudes as $s)
<div class="card" style="padding:12px 14px; margin-bottom:8px;">
    <div style="display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:13px; font-weight:700;">{{ $s->tipo_label }}</div>
            <div style="font-size:12px; color:var(--gris); margin-top:2px;">
                @if($s->tipo === 'correccion_horas')
                    {{ $s->fecha_registro?->format('d/m/Y') }}
                    @if($s->hora_entrada_solicitada || $s->hora_salida_solicitada)
                        · {{ $s->hora_entrada_solicitada ? \Carbon\Carbon::parse($s->hora_entrada_solicitada)->format('H:i') : '—' }}
                        → {{ $s->hora_salida_solicitada ? \Carbon\Carbon::parse($s->hora_salida_solicitada)->format('H:i') : '—' }}
                    @endif
                @elseif($s->fecha_inicio)
                    {{ $s->fecha_inicio->format('d/m/Y') }}
                    @if($s->fecha_fin && !$s->fecha_fin->equalTo($s->fecha_inicio))
                        → {{ $s->fecha_fin->format('d/m/Y') }}
                    @endif
                @endif
            </div>
            @if($s->motivo)
                <div style="font-size:12px; color:var(--gris); margin-top:4px;">{{ $s->motivo }}</div>
            @endif
            @if($s->adjunto_path)
                <a href="{{ asset('storage/' . $s->adjunto_path) }}" target="_blank" style="font-size:11px; color:var(--azul); text-decoration:none;">📎 Ver adjunto</a>
            @endif
            @if($s->estado === 'rechazada' && $s->comentario_revision)
                <div style="font-size:12px; color:var(--rojo); margin-top:4px;">Motivo: {{ $s->comentario_revision }}</div>
            @endif
        </div>
        <span class="badge badge-{{ ['pendiente'=>'amarillo','pendiente_jefe'=>'amarillo','aprobada'=>'verde','rechazada'=>'rojo'][$s->estado] }}">
            {{ $s->estado === 'pendiente_jefe' ? 'ESPERA A TU JEFE' : strtoupper($s->estado) }}
        </span>
    </div>
</div>
@empty
<div class="card" style="text-align:center; padding:40px 20px;">
    <div style="font-size:40px; margin-bottom:12px;">📋</div>
    <div style="font-weight:700; color:var(--gris);">Sin solicitudes</div>
    <div style="font-size:13px; color:var(--gris); margin-top:6px;">
        Aún no has enviado ninguna solicitud.
    </div>
</div>
@endforelse

<div style="margin-top:8px;">
    {{ $solicitudes->links() }}
</div>

@endsection

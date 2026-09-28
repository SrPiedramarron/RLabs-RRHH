@extends('checkin.layout')

@section('title', 'Mis Solicitudes')
@section('header-title', 'Mis Solicitudes')
@section('header-subtitle', 'Vacaciones, permisos y correcciones')

@section('content')

<div class="card" style="padding:16px; display:flex; align-items:center; justify-content:space-between; gap:12px;">
    <div>
        <div style="font-size:12px; color:var(--gris);">Saldo de vacaciones</div>
        <div style="font-size:22px; font-weight:800; color:var(--azul);">
            {{ $saldo['saldo_actual'] ?? '—' }} <span style="font-size:13px; font-weight:600; color:var(--gris);">días</span>
        </div>
    </div>
    <a href="{{ route('checkin.solicitudes.create') }}" class="btn btn-azul" style="width:auto; padding:12px 18px; font-size:14px;">
        + Nueva solicitud
    </a>
</div>

@forelse($solicitudes as $s)
<div class="card" style="padding:12px 14px; margin-bottom:8px;">
    <div style="display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:13px; font-weight:700;">{{ $s->tipo_label }}</div>
            <div style="font-size:12px; color:var(--gris); margin-top:2px;">
                @if($s->fecha_inicio)
                    {{ $s->fecha_inicio->format('d/m/Y') }}
                    @if($s->fecha_fin && !$s->fecha_fin->equalTo($s->fecha_inicio))
                        → {{ $s->fecha_fin->format('d/m/Y') }}
                    @endif
                @endif
            </div>
            @if($s->motivo)
                <div style="font-size:12px; color:var(--gris); margin-top:4px;">{{ $s->motivo }}</div>
            @endif
            @if($s->estado === 'rechazada' && $s->comentario_revision)
                <div style="font-size:12px; color:var(--rojo); margin-top:4px;">Motivo: {{ $s->comentario_revision }}</div>
            @endif
        </div>
        <span class="badge badge-{{ ['pendiente'=>'amarillo','aprobada'=>'verde','rechazada'=>'rojo'][$s->estado] }}">
            {{ strtoupper($s->estado) }}
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

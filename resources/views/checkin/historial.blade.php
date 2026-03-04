@extends('checkin.layout')

@section('title', 'Mis Checkins Remotos')
@section('header-title', 'Checkins Remotos')
@section('header-subtitle', 'Historial de marcaciones')

@section('content')

@forelse($checkins as $checkin)
<div class="card" style="padding:14px 16px; margin-bottom:10px;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
        <div style="display:flex; align-items:center; gap:8px;">
            <span class="badge {{ $checkin->tipo === 'entrada' ? 'badge-verde' : 'badge-rojo' }}">
                {{ strtoupper($checkin->tipo) }}
            </span>
            {{-- Estado facial --}}
            @if($checkin->estado_facial === 'aprobado')
                <span class="badge badge-verde">✅ Facial OK</span>
            @elseif($checkin->estado_facial === 'rechazado')
                <span class="badge badge-rojo">❌ Rechazado</span>
            @elseif($checkin->estado_facial === 'sin_perfil')
                <span class="badge badge-amarillo">⚠️ Sin perfil</span>
            @else
                <span class="badge badge-gris">⏳ Pendiente</span>
            @endif
        </div>
        <div style="font-size:13px; font-weight:700; color:var(--verde);">
            {{ $checkin->fecha_hora->format('H:i') }}
        </div>
    </div>

    <div style="display:flex; align-items:center; justify-content:space-between;">
        <div style="font-size:13px; color:var(--gris);">
            📅 {{ $checkin->fecha_hora->locale('es')->isoFormat('ddd D [de] MMM, YYYY') }}
        </div>
        {{-- Estado procesado --}}
        <span class="badge {{ $checkin->estado_procesado === 'procesado' ? 'badge-verde' : ($checkin->estado_procesado === 'rechazado' ? 'badge-rojo' : 'badge-gris') }}">
            {{ ucfirst($checkin->estado_procesado) }}
        </span>
    </div>

    @if($checkin->latitud && $checkin->longitud)
    <div style="margin-top:8px;">
        <a href="https://maps.google.com/?q={{ $checkin->latitud }},{{ $checkin->longitud }}"
           target="_blank"
           style="font-size:12px; color:var(--verde); text-decoration:none;">
            📍 Ver ubicación en mapa →
        </a>
    </div>
    @endif

    @if($checkin->foto_path)
    <details style="margin-top:8px;">
        <summary style="font-size:12px; color:var(--gris); cursor:pointer;">📷 Ver foto</summary>
        <img src="{{ Storage::url($checkin->foto_path) }}"
             style="width:100%; border-radius:8px; margin-top:8px; max-height:200px; object-fit:cover;">
    </details>
    @endif
</div>
@empty
<div class="card" style="text-align:center; padding:40px 20px;">
    <div style="font-size:40px; margin-bottom:12px;">📋</div>
    <div style="font-weight:700; color:var(--gris);">Sin checkins remotos</div>
    <div style="font-size:13px; color:var(--gris); margin-top:6px;">
        Aún no has marcado de forma remota.
    </div>
</div>
@endforelse

{{ $checkins->links() }}

@endsection

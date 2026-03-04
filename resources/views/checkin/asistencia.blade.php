@extends('checkin.layout')

@section('title', 'Mi Asistencia')
@section('header-title', 'Mi Asistencia')
@section('header-subtitle', \Carbon\Carbon::create($anio, $mes, 1)->locale('es')->isoFormat('MMMM YYYY'))

@section('content')

{{-- Selector de mes --}}
<div class="card" style="padding:12px 16px;">
    <form method="GET" action="{{ route('checkin.asistencia') }}" style="display:flex; gap:8px; align-items:center;">
        <select name="mes" class="form-input" style="flex:1; padding:10px 12px;">
            @foreach($meses as $m)
            <option value="{{ $m->month }}" {{ $m->month == $mes && $m->year == $anio ? 'selected' : '' }}>
                {{ ucfirst($m->locale('es')->isoFormat('MMMM YYYY')) }}
            </option>
            @endforeach
        </select>
        <input type="hidden" name="anio" value="{{ $anio }}">
        <button type="submit" class="btn btn-verde" style="width:auto; padding:10px 18px; font-size:14px;">
            Ver
        </button>
    </form>
</div>

{{-- Resumen --}}
<div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; margin-bottom:16px;">
    <div class="card" style="text-align:center; padding:16px 12px; margin-bottom:0;">
        <div style="font-size:28px; font-weight:800; color:var(--verde);">{{ $totales['presentes'] }}</div>
        <div style="font-size:11px; color:var(--gris); margin-top:2px;">Días Presentes</div>
    </div>
    <div class="card" style="text-align:center; padding:16px 12px; margin-bottom:0;">
        <div style="font-size:28px; font-weight:800; color:var(--amarillo);">{{ $totales['tardanzas'] }}</div>
        <div style="font-size:11px; color:var(--gris); margin-top:2px;">Tardanzas</div>
    </div>
    <div class="card" style="text-align:center; padding:16px 12px; margin-bottom:0;">
        <div style="font-size:28px; font-weight:800; color:var(--rojo);">{{ $totales['ausentes'] }}</div>
        <div style="font-size:11px; color:var(--gris); margin-top:2px;">Ausencias</div>
    </div>
    <div class="card" style="text-align:center; padding:16px 12px; margin-bottom:0;">
        <div style="font-size:28px; font-weight:800; color:var(--gris);">{{ $totales['minutos_tarde'] }}'</div>
        <div style="font-size:11px; color:var(--gris); margin-top:2px;">Min. Tardanza</div>
    </div>
</div>

{{-- Detalle diario --}}
@forelse($records as $record)
@php
    $badgeClass = match($record->estado) {
        'presente'   => 'badge-verde',
        'tarde'      => 'badge-amarillo',
        'ausente'    => 'badge-rojo',
        'feriado'    => 'badge-azul',
        'descanso'   => 'badge-gris',
        default      => 'badge-gris',
    };
    $esRemoto = $record->fuente_entrada === 'remoto' || $record->fuente_salida === 'remoto';
@endphp
<div class="card" style="padding:12px 14px; margin-bottom:8px;">
    <div style="display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:13px; font-weight:700;">
                {{ $record->fecha->locale('es')->isoFormat('ddd D') }}
                @if($esRemoto)
                    <span style="font-size:10px; color:var(--verde); margin-left:4px;">📱 REMOTO</span>
                @endif
            </div>
            <div style="font-size:12px; color:var(--gris); margin-top:2px;">
                @if($record->hora_entrada)
                    {{ \Carbon\Carbon::parse($record->hora_entrada)->format('H:i') }}
                    @if($record->hora_salida)
                        → {{ \Carbon\Carbon::parse($record->hora_salida)->format('H:i') }}
                    @endif
                @else
                    —
                @endif
                @if($record->minutos_tarde > 0)
                    · <span style="color:var(--amarillo);">+{{ $record->minutos_tarde }}' tarde</span>
                @endif
            </div>
        </div>
        <span class="badge {{ $badgeClass }}">{{ strtoupper($record->estado) }}</span>
    </div>
</div>
@empty
<div class="card" style="text-align:center; padding:40px 20px;">
    <div style="font-size:40px; margin-bottom:12px;">📅</div>
    <div style="font-weight:700; color:var(--gris);">Sin registros</div>
    <div style="font-size:13px; color:var(--gris); margin-top:6px;">
        No hay registros para este mes.
    </div>
</div>
@endforelse

@endsection

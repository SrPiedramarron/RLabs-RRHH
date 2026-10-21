@extends('checkin.layout')

@section('title', 'Beneficios')
@section('header-title', 'Beneficios')
@section('header-subtitle', 'Más puntualidad, más sábados libres')

@section('content')

@php
    $u = rtrim(rtrim(number_format($umbral, 1, '.', ''), '0'), '.');
    $excelente = fn ($pct) => $pct !== null && $pct >= $umbral;
@endphp

<div style="font-size:12px; color:var(--gris); margin:0 4px 10px;">Aplica solo al personal sujeto a fiscalización.</div>

{{-- Niveles --}}
<div class="card" style="padding:0; overflow:hidden; border-left:6px solid #10B981;">
    <div style="padding:14px 16px;">
        <div style="font-size:18px; font-weight:800;">Excelente</div>
        <div style="font-size:12px; color:var(--gris);">{{ $u }}% – 100%</div>
        <div style="font-size:15px; font-weight:700; margin-top:8px;">Todos los sábados libres</div>
    </div>
</div>

<div class="card" style="padding:0; overflow:hidden; border-left:6px solid #EF4444;">
    <div style="padding:14px 16px;">
        <div style="font-size:18px; font-weight:800;">Sin beneficio</div>
        <div style="font-size:12px; color:var(--gris);">Menos de {{ $u }}%</div>
        <div style="font-size:15px; font-weight:700; margin-top:8px;">Vienes todos los sábados</div>
    </div>
</div>

{{-- Tu resultado --}}
<div class="card" style="padding:16px;">
    <div class="card-title">Tu resultado</div>

    <div style="font-size:12px; color:var(--gris); margin-bottom:4px;">
        Último corte cerrado · {{ $cerradoDesde->format('d/m') }} al {{ $cerradoHasta->format('d/m') }}
    </div>
    @if($cerrado['pct'] === null)
        <div style="font-size:14px; margin-bottom:14px;">Sin marcaciones en ese periodo.</div>
    @else
        <div style="font-size:30px; font-weight:800; color:{{ $excelente($cerrado['pct']) ? '#059669' : '#DC2626' }};">{{ number_format($cerrado['pct'], 1) }}%</div>
        <div style="font-size:13px; margin-bottom:4px;">{{ $cerrado['puntual'] }} días puntual · {{ $cerrado['tarde'] }} días tarde</div>
        <div style="font-size:14px; font-weight:700; margin-bottom:14px; color:{{ $excelente($cerrado['pct']) ? '#059669' : '#DC2626' }};">
            {{ $excelente($cerrado['pct']) ? 'Excelente: todos los sábados libres' : 'Sin beneficio: vienes todos los sábados' }}
        </div>
    @endif

    <div style="font-size:12px; color:var(--gris); margin-bottom:4px; border-top:1px solid rgba(128,128,128,.2); padding-top:12px;">
        Corte en curso · {{ $desde->format('d/m') }} al {{ $hasta->format('d/m') }} (avance)
    </div>
    @if($enCurso['pct'] === null)
        <div style="font-size:14px;">Aún sin marcaciones en este periodo.</div>
    @else
        <div style="font-size:22px; font-weight:800; color:{{ $excelente($enCurso['pct']) ? '#059669' : '#DC2626' }};">{{ number_format($enCurso['pct'], 1) }}%</div>
        <div style="font-size:13px;">{{ $enCurso['puntual'] }} días puntual · {{ $enCurso['tarde'] }} días tarde</div>
    @endif
</div>

{{-- Detalles --}}
<div class="card" style="padding:16px;">
    <div class="card-title">Detalles de cálculo y validación</div>
    <div style="font-size:13px; font-weight:700; margin-top:6px;">Corte del período</div>
    <div style="font-size:13px; color:var(--gris); margin-bottom:10px;">Del 26 del mes anterior al 25 del mes en curso.</div>
    <div style="font-size:13px; font-weight:700;">Cómo se calcula</div>
    <div style="font-size:13px; color:var(--gris); margin-bottom:10px;">% de puntualidad = días puntual ÷ (días puntual + días tarde). Las ausencias, feriados, descansos, permisos y vacaciones no cuentan.</div>
    <div style="font-size:13px; font-weight:700;">Validación por App</div>
    <div style="font-size:13px; color:var(--gris);">Puedes validar tu porcentaje mensual desde esta App el mes siguiente al corte.</div>
</div>

{{-- Escala --}}
<div class="card" style="padding:16px;">
    <div class="card-title">Escala de beneficios</div>
    <table style="width:100%; table-layout:fixed; border-collapse:collapse; font-size:12px; text-align:center;">
        <colgroup>
            <col style="width:28%"><col style="width:18%"><col style="width:18%"><col style="width:18%"><col style="width:18%">
        </colgroup>
        <tr style="background:#DBEAFE;">
            <th rowspan="2" style="padding:6px 4px; font-size:11px;">Cumpli-<br>miento</th>
            <th colspan="2" style="padding:6px 2px; font-size:11px;">Mes de<br>4 sábados</th>
            <th colspan="2" style="padding:6px 2px; font-size:11px;">Mes de<br>5 sábados</th>
        </tr>
        <tr style="background:#DBEAFE;">
            <th style="padding:4px 2px; font-size:10px; font-weight:600;">Libres</th><th style="padding:4px 2px; font-size:10px; font-weight:600;">Trab.</th>
            <th style="padding:4px 2px; font-size:10px; font-weight:600;">Libres</th><th style="padding:4px 2px; font-size:10px; font-weight:600;">Trab.</th>
        </tr>
        <tr style="border-bottom:1px solid #E5E7EB;">
            <td style="padding:10px 4px; background:#10B981; color:#fff; font-weight:700; font-size:11px;">Excelente</td>
            <td style="font-weight:700; font-size:15px;">4</td><td style="font-weight:700; font-size:15px;">0</td>
            <td style="font-weight:700; font-size:15px;">5</td><td style="font-weight:700; font-size:15px;">0</td>
        </tr>
        <tr>
            <td style="padding:10px 4px; background:#EF4444; color:#fff; font-weight:700; font-size:11px;">Sin beneficio</td>
            <td style="font-weight:700; font-size:15px;">0</td><td style="font-weight:700; font-size:15px;">4</td>
            <td style="font-weight:700; font-size:15px;">0</td><td style="font-weight:700; font-size:15px;">5</td>
        </tr>
    </table>
    <div style="font-size:11px; color:var(--gris); margin-top:8px;">Trab. = sábados trabajados.</div>
</div>

@endsection

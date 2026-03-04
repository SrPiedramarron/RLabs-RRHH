{{-- resources/views/reports/sunafil-pdf.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1a1a1a; }

        .header { text-align: center; margin-bottom: 8px; border-bottom: 2px solid #2563EB; padding-bottom: 6px; }
        .header h1 { font-size: 13px; font-weight: bold; color: #1e3a5f; }
        .header h2 { font-size: 10px; color: #444; margin-top: 2px; }
        .header .meta { font-size: 8px; color: #666; margin-top: 4px; }

        .filtro-badge {
            display: inline-block;
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            color: #1D4ED8;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 7px;
            margin: 2px;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        thead tr { background-color: #2563EB; color: white; }
        thead th {
            padding: 4px 3px;
            text-align: center;
            font-size: 7px;
            font-weight: bold;
            border: 1px solid #1D4ED8;
        }
        tbody tr:nth-child(even) { background-color: #F8FAFC; }
        tbody tr:hover { background-color: #EFF6FF; }
        tbody td {
            padding: 3px 3px;
            border: 1px solid #E2E8F0;
            font-size: 7px;
            text-align: center;
        }
        tbody td.text-left { text-align: left; }

        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 6.5px;
            font-weight: bold;
        }
        .badge-puntual  { background: #D1FAE5; color: #065F46; }
        .badge-tardanza { background: #FEF3C7; color: #92400E; }
        .badge-ausente  { background: #FEE2E2; color: #991B1B; }
        .badge-feriado  { background: #E5E7EB; color: #374151; }

        .tardanza-min { color: #B45309; font-weight: bold; }

        .footer {
            margin-top: 10px;
            border-top: 1px solid #E2E8F0;
            padding-top: 4px;
            font-size: 7px;
            color: #9CA3AF;
            display: flex;
            justify-content: space-between;
        }
        .resumen {
            margin-top: 6px;
            background: #F0F9FF;
            border: 1px solid #BAE6FD;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 7.5px;
        }
        .resumen strong { color: #0369A1; }

        .page-break { page-break-after: always; }
    </style>
</head>
<body>

{{-- ENCABEZADO --}}
<div class="header">
    <h1>{{ $meta['empresa_razon_social'] }}</h1>
    <h2>REGISTRO DE CONTROL DE ASISTENCIA Y PUNTUALIDAD</h2>
    <div class="meta">
        RUC: {{ $meta['empresa_ruc'] }} &nbsp;|&nbsp; {{ $meta['empresa_direccion'] }}
        &nbsp;|&nbsp; Período: <strong>{{ $meta['fecha_inicio'] }}</strong> al <strong>{{ $meta['fecha_fin'] }}</strong>
    </div>
    <div style="margin-top: 4px;">
        @if($meta['location'])
            <span class="filtro-badge">📍 Sede: {{ $meta['location']->nombre }}</span>
        @else
            <span class="filtro-badge">📍 Todas las sedes</span>
        @endif
        @if($meta['department'])
            <span class="filtro-badge">🏢 Área: {{ $meta['department']->nombre }}</span>
        @else
            <span class="filtro-badge">🏢 Todos los departamentos</span>
        @endif
        @if($incluye_refrigerio)
            <span class="filtro-badge" style="background:#F0FDF4;border-color:#BBF7D0;color:#166534;">🍽 Incluye refrigerio</span>
        @endif
    </div>
</div>

{{-- TABLA PRINCIPAL --}}
<table>
    <thead>
        <tr>
            <th style="width:25px">N°</th>
            <th style="width:110px; text-align:left">Apellidos y Nombres</th>
            <th style="width:50px">DNI/CE</th>
            <th style="width:65px">Área</th>
            <th style="width:55px">Sede</th>
            <th style="width:45px">Fecha</th>
            <th style="width:28px">Día</th>
            <th style="width:38px">Ingreso</th>
            <th style="width:38px">Salida</th>
            @if($incluye_refrigerio)
                <th style="width:38px">Ini. Refrig.</th>
                <th style="width:38px">Fin Refrig.</th>
            @endif
            <th style="width:38px">H. Trab.</th>
            <th style="width:42px">Estado</th>
            <th style="width:38px">Tardanza</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        @php $n = 0; @endphp
        @foreach($records as $record)
            @php
                $n++;
                $horasTrabajadas = '—';
                if ($record->hora_entrada && $record->hora_salida) {
                    $entrada = \Carbon\Carbon::parse($record->fecha . ' ' . $record->hora_entrada);
                    $salida  = \Carbon\Carbon::parse($record->fecha . ' ' . $record->hora_salida);
                    $mins    = $salida->diffInMinutes($entrada);
                    if ($incluye_refrigerio && $record->inicio_refrigerio && $record->fin_refrigerio) {
                        $iniRef = \Carbon\Carbon::parse($record->fecha . ' ' . $record->inicio_refrigerio);
                        $finRef = \Carbon\Carbon::parse($record->fecha . ' ' . $record->fin_refrigerio);
                        $mins -= $finRef->diffInMinutes($iniRef);
                    }
                    $horasTrabajadas = sprintf('%02d:%02d', intdiv($mins,60), $mins % 60);
                }
                $estadoBadge = match($record->estado) {
                    'puntual'  => 'badge-puntual',
                    'tardanza' => 'badge-tardanza',
                    'ausente'  => 'badge-ausente',
                    'feriado'  => 'badge-feriado',
                    default    => '',
                };
            @endphp
            <tr>
                <td>{{ $n }}</td>
                <td class="text-left">
                    {{ strtoupper($record->employee->apellidos ?? '') }},
                    {{ $record->employee->nombres ?? '' }}
                </td>
                <td>{{ $record->employee->dni ?? '' }}</td>
                <td>{{ $record->employee->department->nombre ?? '—' }}</td>
                <td>{{ $record->employee->location->nombre ?? '—' }}</td>
                <td>{{ \Carbon\Carbon::parse($record->fecha)->format('d/m/Y') }}</td>
                <td>{{ substr(\Carbon\Carbon::parse($record->fecha)->locale('es')->isoFormat('ddd'), 0, 3) }}</td>
                <td>{{ $record->hora_entrada ?? '—' }}</td>
                <td>{{ $record->hora_salida ?? '—' }}</td>
                @if($incluye_refrigerio)
                    <td>{{ $record->inicio_refrigerio ?? '—' }}</td>
                    <td>{{ $record->fin_refrigerio ?? '—' }}</td>
                @endif
                <td>{{ $horasTrabajadas }}</td>
                <td><span class="badge {{ $estadoBadge }}">{{ ucfirst($record->estado ?? '') }}</span></td>
                <td class="{{ $record->minutos_tarde > 0 ? 'tardanza-min' : '' }}">
                    {{ $record->minutos_tarde > 0 ? $record->minutos_tarde . ' min' : '—' }}
                </td>
                <td class="text-left">{{ $record->observaciones ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- RESUMEN --}}
@php
    $totalPuntual  = $records->where('estado', 'puntual')->count();
    $totalTardanza = $records->where('minutos_tarde', '>', 0)->count();
    $totalAusente  = $records->where('estado', 'ausente')->count();
    $totalFeriado  = $records->where('estado', 'feriado')->count();
    $sinSalida     = $records->whereNull('hora_salida')->where('estado', '!=', 'ausente')->count();
    $totalMinTard  = $records->sum('minutos_tarde');
@endphp
<div class="resumen">
    <strong>Resumen del período:</strong> &nbsp;
    Total registros: <strong>{{ $records->count() }}</strong> &nbsp;|&nbsp;
    Puntual: <strong>{{ $totalPuntual }}</strong> &nbsp;|&nbsp;
    Con tardanza: <strong>{{ $totalTardanza }}</strong> &nbsp;|&nbsp;
    Ausencias: <strong>{{ $totalAusente }}</strong> &nbsp;|&nbsp;
    Feriados: <strong>{{ $totalFeriado }}</strong> &nbsp;|&nbsp;
    Sin marcar salida: <strong>{{ $sinSalida }}</strong> &nbsp;|&nbsp;
    Total minutos tardanza: <strong>{{ $totalMinTard }} min</strong>
    @if($incluye_refrigerio)
        &nbsp;|&nbsp; <span style="color:#166534">🍽 Refrigerio incluido en cálculo</span>
    @endif
</div>

{{-- PIE DE PÁGINA --}}
<div class="footer">
    <span>Generado por: {{ $meta['generado_por'] }} | {{ $meta['generado_en'] }}</span>
    <span>AsistenciaRLabs &copy; {{ date('Y') }} | Sistema de Control de Asistencia</span>
    <span>DS 004-2006-TR | Res. 0055-2025-SUNAFIL</span>
</div>

</body>
</html>

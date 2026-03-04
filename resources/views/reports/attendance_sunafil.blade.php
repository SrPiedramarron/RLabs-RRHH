<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 8px; color: #333; }

        .header { text-align: center; margin-bottom: 10px; border-bottom: 2px solid #1a7f4b; padding-bottom: 8px; }
        .header h1 { font-size: 12px; color: #1a7f4b; text-transform: uppercase; margin-bottom: 4px; }
        .header h2 { font-size: 10px; margin-bottom: 2px; }
        .header p { font-size: 8px; color: #666; }

        .info-grid { display: table; width: 100%; margin-bottom: 8px; }
        .info-row { display: table-row; }
        .info-cell { display: table-cell; padding: 2px 4px; font-size: 8px; }
        .info-label { font-weight: bold; color: #1a7f4b; width: 80px; }

        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        thead tr { background-color: #1a7f4b; color: white; }
        thead th { padding: 4px 3px; text-align: center; font-size: 7px; border: 1px solid #999; }
        tbody tr:nth-child(even) { background-color: #f5f5f5; }
        tbody tr:hover { background-color: #e8f5e9; }
        tbody td { padding: 3px 3px; border: 1px solid #ddd; font-size: 7px; text-align: center; }
        tbody td.nombre { text-align: left; }

        .badge { padding: 1px 4px; border-radius: 3px; font-size: 6px; font-weight: bold; }
        .badge-presente { background: #d4edda; color: #155724; }
        .badge-tarde { background: #fff3cd; color: #856404; }
        .badge-ausente { background: #f8d7da; color: #721c24; }
        .badge-feriado { background: #cce5ff; color: #004085; }
        .badge-descanso { background: #e2e3e5; color: #383d41; }
        .badge-permiso { background: #cce5ff; color: #004085; }
        .badge-vacaciones { background: #cce5ff; color: #004085; }

        .footer { margin-top: 15px; border-top: 1px solid #ddd; padding-top: 8px; }
        .footer-grid { display: table; width: 100%; }
        .footer-cell { display: table-cell; text-align: center; width: 33%; padding-top: 20px; }
        .footer-line { border-top: 1px solid #333; margin: 0 20px; padding-top: 4px; font-size: 7px; }

        .resumen { margin: 8px 0; padding: 6px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; }
        .resumen-title { font-weight: bold; color: #1a7f4b; margin-bottom: 4px; font-size: 8px; }
        .resumen-grid { display: table; width: 100%; }
        .resumen-item { display: table-cell; text-align: center; font-size: 7px; }
        .resumen-num { font-size: 12px; font-weight: bold; color: #1a7f4b; display: block; }

        .page-break { page-break-after: always; }
        .legal { font-size: 6px; color: #999; margin-top: 6px; text-align: center; }
    </style>
</head>
<body>

    {{-- ENCABEZADO --}}
    <div class="header">
        <h1>Registro de Control de Asistencia</h1>
        <h2>{{ $company->razon_social }}</h2>
        <p>RUC: {{ $company->ruc }} | {{ $company->direccion }}</p>
        <p>
            Período: {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
            @if($location) | Sede: {{ $location->nombre }} @endif
        </p>
        <p class="legal">Documento generado en cumplimiento del D.S. N° 004-2006-TR y modificatorias</p>
    </div>

    {{-- RESUMEN --}}
    <div class="resumen">
        <div class="resumen-title">Resumen del Período</div>
        <div class="resumen-grid">
            <div class="resumen-item">
                <span class="resumen-num">{{ $totales['presentes'] }}</span>
                Presentes
            </div>
            <div class="resumen-item">
                <span class="resumen-num">{{ $totales['tardanzas'] }}</span>
                Tardanzas
            </div>
            <div class="resumen-item">
                <span class="resumen-num">{{ $totales['ausentes'] }}</span>
                Ausencias
            </div>
            <div class="resumen-item">
                <span class="resumen-num">{{ $totales['horas_extra'] }}</span>
                H. Extra Total
            </div>
            <div class="resumen-item">
                <span class="resumen-num">{{ $totales['total_empleados'] }}</span>
                Empleados
            </div>
        </div>
    </div>

    {{-- TABLA --}}
    <table>
        <thead>
            <tr>
                <th>FECHA</th>
                <th>APELLIDOS Y NOMBRES</th>
                <th>DNI</th>
                <th>CARGO</th>
                <th>H. ENTRADA</th>
                <th>H. SALIDA</th>
                <th>TARDANZA</th>
                <th>H. ORD.</th>
                <th>H. EXT. DIURNA (25%)</th>
                <th>H. EXT. NOCT. (35%)</th>
                <th>ESTADO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
            <tr>
                <td>{{ $record->fecha->format('d/m/Y') }}</td>
                <td class="nombre">{{ $record->employee->nombre_completo }}</td>
                <td>{{ $record->employee->dni }}</td>
                <td>{{ $record->employee->cargo ?? '—' }}</td>
                <td>{{ $record->hora_entrada?->format('H:i') ?? '—' }}</td>
                <td>{{ $record->hora_salida?->format('H:i') ?? '—' }}</td>
                <td>{{ $record->minutos_tarde > 0 ? $record->tiempo_tarde : '—' }}</td>
                <td>{{ $record->horas_ordinarias }}</td>
                <td>{{ $record->horas_extra_diurnas > 0 ? $record->horas_extra_diurnas : '—' }}</td>
                <td>{{ $record->horas_extra_nocturnas > 0 ? $record->horas_extra_nocturnas : '—' }}</td>
                <td>
                    <span class="badge badge-{{ $record->estado }}">
                        {{ strtoupper($record->estado) }}
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="11" style="text-align:center; padding: 20px; color: #999;">
                    No hay registros para el período seleccionado
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- FIRMAS --}}
    <div class="footer">
        <div class="footer-grid">
            <div class="footer-cell">
                <div class="footer-line">Responsable de RR.HH.</div>
            </div>
            <div class="footer-cell">
                <div class="footer-line">Gerencia General</div>
            </div>
            <div class="footer-cell">
                <div class="footer-line">Sello de la Empresa</div>
            </div>
        </div>
    </div>

</body>
</html>
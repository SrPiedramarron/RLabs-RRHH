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

        .employee-card { 
            display: table; width: 100%; margin-bottom: 10px; 
            border: 1px solid #ddd; border-radius: 4px; padding: 8px;
            background: #f8f9fa;
        }
        .employee-card-row { display: table-row; }
        .employee-card-cell { display: table-cell; padding: 2px 8px; font-size: 8px; width: 25%; }
        .employee-label { font-weight: bold; color: #1a7f4b; display: block; }

        .resumen {
            display: table; width: 100%; margin-bottom: 10px;
            border: 1px solid #1a7f4b; border-radius: 4px;
        }
        .resumen-item { display: table-cell; text-align: center; padding: 8px 4px; border-right: 1px solid #ddd; }
        .resumen-item:last-child { border-right: none; }
        .resumen-num { font-size: 14px; font-weight: bold; color: #1a7f4b; display: block; }
        .resumen-label { font-size: 7px; color: #666; }

        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        thead tr { background-color: #1a7f4b; color: white; }
        thead th { padding: 4px 3px; text-align: center; font-size: 7px; border: 1px solid #999; }
        tbody tr:nth-child(even) { background-color: #f5f5f5; }
        tbody td { padding: 3px; border: 1px solid #ddd; font-size: 7px; text-align: center; }

        .badge { padding: 1px 4px; border-radius: 3px; font-size: 6px; font-weight: bold; }
        .badge-presente   { background: #d4edda; color: #155724; }
        .badge-tarde      { background: #fff3cd; color: #856404; }
        .badge-ausente    { background: #f8d7da; color: #721c24; }
        .badge-feriado    { background: #cce5ff; color: #004085; }
        .badge-descanso   { background: #e2e3e5; color: #383d41; }
        .badge-permiso    { background: #cce5ff; color: #004085; }
        .badge-vacaciones { background: #cce5ff; color: #004085; }

        .footer { margin-top: 20px; border-top: 1px solid #ddd; padding-top: 8px; }
        .footer-grid { display: table; width: 100%; }
        .footer-cell { display: table-cell; text-align: center; width: 33%; padding-top: 30px; }
        .footer-line { border-top: 1px solid #333; margin: 0 20px; padding-top: 4px; font-size: 7px; }
        .legal { font-size: 6px; color: #999; margin-top: 6px; text-align: center; }
    </style>
</head>
<body>

    {{-- ENCABEZADO --}}
    <div class="header">
        <h1>Reporte Mensual de Asistencia</h1>
        <h2>{{ $company->razon_social }}</h2>
        <p>RUC: {{ $company->ruc }} | {{ $company->direccion }}</p>
        <p>Período: {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p>
        <p class="legal">Documento generado en cumplimiento del D.S. N° 004-2006-TR y modificatorias</p>
    </div>

    {{-- DATOS DEL EMPLEADO --}}
    <div class="employee-card">
        <div class="employee-card-row">
            <div class="employee-card-cell">
                <span class="employee-label">Apellidos y Nombres</span>
                {{ $employee->nombre_completo }}
            </div>
            <div class="employee-card-cell">
                <span class="employee-label">DNI</span>
                {{ $employee->dni }}
            </div>
            <div class="employee-card-cell">
                <span class="employee-label">Cargo</span>
                {{ $employee->cargo ?? '—' }}
            </div>
            <div class="employee-card-cell">
                <span class="employee-label">Sede</span>
                {{ $employee->location->nombre }}
            </div>
        </div>
        <div class="employee-card-row">
            <div class="employee-card-cell">
                <span class="employee-label">Horario</span>
                {{ $employee->schedule->nombre }}
            </div>
            <div class="employee-card-cell">
                <span class="employee-label">Entrada / Salida</span>
                {{ $employee->schedule->hora_entrada }} — {{ $employee->schedule->hora_salida }}
            </div>
            <div class="employee-card-cell">
                <span class="employee-label">Fecha Ingreso</span>
                {{ $employee->fecha_ingreso->format('d/m/Y') }}
            </div>
            <div class="employee-card-cell">
                <span class="employee-label">Área</span>
                {{ $employee->department->nombre ?? '—' }}
            </div>
        </div>
    </div>

    {{-- RESUMEN --}}
    <div class="resumen">
        <div class="resumen-item">
            <span class="resumen-num">{{ $totales['dias_laborables'] }}</span>
            <span class="resumen-label">Días Laborables</span>
        </div>
        <div class="resumen-item">
            <span class="resumen-num">{{ $totales['presentes'] }}</span>
            <span class="resumen-label">Presentes</span>
        </div>
        <div class="resumen-item">
            <span class="resumen-num">{{ $totales['tardanzas'] }}</span>
            <span class="resumen-label">Tardanzas</span>
        </div>
        <div class="resumen-item">
            <span class="resumen-num">{{ $totales['ausentes'] }}</span>
            <span class="resumen-label">Ausencias</span>
        </div>
        <div class="resumen-item">
            <span class="resumen-num">{{ $totales['minutos_tarde'] }}</span>
            <span class="resumen-label">Min. Tardanza</span>
        </div>
        <div class="resumen-item">
            <span class="resumen-num">{{ $totales['horas_ordinarias'] }}</span>
            <span class="resumen-label">H. Ordinarias</span>
        </div>
        <div class="resumen-item">
            <span class="resumen-num">{{ $totales['horas_extra_diurnas'] }}</span>
            <span class="resumen-label">H. Extra Diurnas</span>
        </div>
        <div class="resumen-item">
            <span class="resumen-num">{{ $totales['horas_extra_nocturnas'] }}</span>
            <span class="resumen-label">H. Extra Noct.</span>
        </div>
    </div>

    {{-- DETALLE DIARIO --}}
    <table>
        <thead>
            <tr>
                <th>FECHA</th>
                <th>DÍA</th>
                <th>H. ENTRADA</th>
                <th>H. SALIDA</th>
                <th>TARDANZA</th>
                <th>H. ORDINARIAS</th>
                <th>H. EXTRA DIURNA (25%)</th>
                <th>H. EXTRA NOCT. (35%)</th>
                <th>ESTADO</th>
                <th>OBSERVACIÓN</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
            <tr>
                <td>{{ $record->fecha->format('d/m/Y') }}</td>
                <td>{{ ucfirst($record->fecha->locale('es')->dayName) }}</td>
                <td>{{ $record->hora_entrada?->format('H:i') ?? '—' }}</td>
                <td>{{ $record->hora_salida?->format('H:i') ?? '—' }}</td>
                <td>{{ $record->minutos_tarde > 0 ? $record->tiempo_tarde : '—' }}</td>
                <td>{{ $record->horas_ordinarias > 0 ? $record->horas_ordinarias : '—' }}</td>
                <td>{{ $record->horas_extra_diurnas > 0 ? $record->horas_extra_diurnas : '—' }}</td>
                <td>{{ $record->horas_extra_nocturnas > 0 ? $record->horas_extra_nocturnas : '—' }}</td>
                <td>
                    <span class="badge badge-{{ $record->estado }}">
                        {{ strtoupper($record->estado) }}
                    </span>
                </td>
                <td>{{ $record->observacion ?? '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="10" style="text-align:center; padding:10px; color:#999;">
                    Sin registros
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- FIRMAS --}}
    <div class="footer">
        <div class="footer-grid">
            <div class="footer-cell">
                <div class="footer-line">Firma del Trabajador</div>
            </div>
            <div class="footer-cell">
                <div class="footer-line">Responsable de RR.HH.</div>
            </div>
            <div class="footer-cell">
                <div class="footer-line">Sello de la Empresa</div>
            </div>
        </div>
    </div>

</body>
</html>
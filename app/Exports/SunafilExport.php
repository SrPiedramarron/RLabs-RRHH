<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SunafilExport implements
    FromArray,
    WithHeadings,
    WithStyles,
    ShouldAutoSize,
    WithTitle
{
    private \Illuminate\Support\Collection $sorted;
    private array $subtotalRows = [];
    private array $geolocalizacionPorRecord = [];

    public function __construct(
        private Collection $records,
        private array $meta,
        private bool $incluyeRefrigerio = false,
        private bool $incluyeGeolocalizacion = false
    ) {
        if ($this->incluyeGeolocalizacion) {
            $recordIds = $records->pluck('id')->filter()->values();

            \App\Models\RemoteCheckin::whereIn('attendance_record_id', $recordIds)
                ->whereNotNull('latitud')
                ->orderBy('fecha_hora')
                ->get(['attendance_record_id', 'tipo', 'latitud', 'longitud'])
                ->each(function ($checkin) {
                    // Si hay más de una marcación remota el mismo día (ej. reintentos),
                    // se queda con la primera entrada y la primera salida.
                    $this->geolocalizacionPorRecord[$checkin->attendance_record_id][$checkin->tipo] ??= [
                        'lat' => $checkin->latitud,
                        'lng' => $checkin->longitud,
                    ];
                });
        }
        // Construir colección expandida con todos los días del período por empleado
        $desde = \Carbon\Carbon::parse($meta['fecha_inicio_raw']);
        $hasta = \Carbon\Carbon::parse($meta['fecha_fin_raw']);

        // Indexar registros reales por employee_id + fecha
        $index = [];
        foreach ($records as $record) {
            $key = $record->employee_id . '_' . \Carbon\Carbon::parse($record->fecha)->toDateString();
            $index[$key] = $record;
        }

        // Empleados únicos, ordenados por apellido
        $empleados = $records->map(fn($r) => $r->employee)
            ->unique('id')
            ->sortBy(fn($e) => ($e->apellidos ?? '') . ($e->nombres ?? ''))
            ->values();

        $expanded = collect();

        foreach ($empleados as $employee) {
            $fecha = $desde->copy();
            while ($fecha->lte($hasta)) {
                $dateStr = $fecha->toDateString();
                $key     = $employee->id . '_' . $dateStr;

                if (isset($index[$key])) {
                    $expanded->push($index[$key]);
                } else {
                    // Crear registro vacío
                    $dummy = new \App\Models\AttendanceRecord([
                        'employee_id'  => $employee->id,
                        'fecha'        => $dateStr,
                        'hora_entrada' => null,
                        'hora_salida'  => null,
                        'estado'       => $fecha->isSunday() ? 'descanso' : 'ausente',
                        'minutos_tarde'          => 0,
                        'horas_ordinarias'       => 0,
                        'horas_extra_diurnas'    => 0,
                        'horas_extra_nocturnas'  => 0,
                        'inicio_refrigerio'      => null,
                        'fin_refrigerio'         => null,
                        'observacion'            => null,
                    ]);
                    $dummy->setRelation('employee', $employee);
                    $expanded->push($dummy);
                }

                $fecha->addDay();
            }
        }

        $this->sorted = $expanded;
    }

    private function decimalToHHMM(?float $decimal): string
    {
        if (!$decimal || $decimal <= 0) return '—';
        $total = (int) round($decimal * 60);
        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    private function minutosToHHMM(int $minutos): string
    {
        if ($minutos <= 0) return '00:00';
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    /**
     * Convierte horas_ordinarias (decimal en BD) a minutos enteros.
     * Usa el valor ya procesado por AttendanceProcessor, que descuenta
     * correctamente el refrigerio según el horario real del día.
     */
    private function horasOrdinariasAMinutos($record): int
    {
        $horas = (float) ($record->horas_ordinarias ?? 0);
        if ($horas <= 0) return 0;
        return (int) round($horas * 60);
    }

    private function formatHorasTrabajadas($record): string
    {
        $minutos = $this->horasOrdinariasAMinutos($record);
        if ($minutos === 0) return '—';
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    /**
     * Coordenadas de la marcación remota de ese día: prioriza la de entrada
     * (es la que normalmente responde "desde dónde marcó"); si solo la
     * salida fue remota, usa esa. Si ninguna fue remota (o no hay
     * geolocalización activada), devuelve vacío.
     */
    private function resolverGeolocalizacion($record): array
    {
        $geo = $this->geolocalizacionPorRecord[$record->id ?? null] ?? null;

        $punto = $geo['entrada'] ?? $geo['salida'] ?? null;

        return $punto ? [$punto['lat'], $punto['lng']] : ['—', '—'];
    }

    public function array(): array
    {
        $estadoMap = [
            'presente'   => 'Presente',
            'tarde'      => 'Tarde',
            'ausente'    => 'Ausente',
            'descanso'   => 'Descanso',
            'feriado'    => 'Feriado',
            'permiso'    => 'Permiso',
            'vacaciones' => 'Vacaciones',
        ];

        $rows         = [];
        $contador     = 0;
        $currentDni   = null;
        $acumMinutos  = 0;
        $acumTardanza = 0;
        $acumExtra25  = 0.0;
        $acumExtra35  = 0.0;
        $rowIndex     = 0;

        foreach ($this->sorted as $record) {
            $dni = $record->employee->dni ?? '';

            // Cambió de empleado → insertar fila de subtotal del anterior
            if ($currentDni !== null && $dni !== $currentDni) {
                $rows[]               = $this->buildSubtotalRow($acumMinutos, $acumTardanza, $acumExtra25, $acumExtra35);
                $rowIndex++;
                $this->subtotalRows[] = $rowIndex;
                $acumMinutos  = 0;
                $acumTardanza = 0;
                $acumExtra25  = 0.0;
                $acumExtra35  = 0.0;
            }

            $currentDni    = $dni;
            $contador++;
            $acumMinutos  += $this->horasOrdinariasAMinutos($record);
            $acumTardanza += (int)   ($record->minutos_tarde        ?? 0);
            $acumExtra25  += (float) ($record->horas_extra_diurnas  ?? 0);
            $acumExtra35  += (float) ($record->horas_extra_nocturnas ?? 0);

            $row = [
                $contador,
                trim(($record->employee->apellidos ?? '') . ', ' . ($record->employee->nombres ?? '')),
                $dni,
                $record->employee->department->nombre ?? '—',
                $record->employee->location->nombre   ?? '—',
                Carbon::parse($record->fecha)->format('d/m/Y'),
                Carbon::parse($record->fecha)->locale('es')->isoFormat('dddd'),
                $record->hora_entrada ? Carbon::parse($record->hora_entrada)->format('H:i') : '—',
                $record->hora_salida  ? Carbon::parse($record->hora_salida)->format('H:i')  : '—',
            ];

            if ($this->incluyeRefrigerio) {
                $row[] = $record->inicio_refrigerio ? Carbon::parse($record->inicio_refrigerio)->format('H:i') : '—';
                $row[] = $record->fin_refrigerio    ? Carbon::parse($record->fin_refrigerio)->format('H:i')    : '—';
            }

            $row = array_merge($row, [
                $this->formatHorasTrabajadas($record),
                $estadoMap[$record->estado] ?? ucfirst($record->estado ?? '—'),
                $record->minutos_tarde > 0
                    ? sprintf('%02d:%02d', intdiv($record->minutos_tarde, 60), $record->minutos_tarde % 60)
                    : '—',
                $this->decimalToHHMM($record->horas_extra_diurnas),
                $this->decimalToHHMM($record->horas_extra_nocturnas),
                $record->observacion ?? '',
            ]);

            if ($this->incluyeGeolocalizacion) {
                $row = array_merge($row, $this->resolverGeolocalizacion($record));
            }

            $rows[] = $row;
            $rowIndex++;
        }

        // Subtotal del último empleado
        if ($currentDni !== null) {
            $rows[]               = $this->buildSubtotalRow($acumMinutos, $acumTardanza, $acumExtra25, $acumExtra35);
            $rowIndex++;
            $this->subtotalRows[] = $rowIndex;
        }

        return $rows;
    }

    private function buildSubtotalRow(int $acumMinutos, int $acumTardanza, float $acumExtra25, float $acumExtra35): array
    {
        $cols = $this->incluyeRefrigerio ? 11 : 9; // columnas antes de "Horas Trabajadas"
        $totalCols = $cols + 6 + ($this->incluyeGeolocalizacion ? 2 : 0); // +6: Horas..Observación; +2: Latitud/Longitud
        $row  = array_fill(0, $totalCols, '');

        $row[1]         = 'TOTALES DEL PERÍODO';
        $row[$cols]     = $this->minutosToHHMM($acumMinutos);       // Horas Trabajadas
        $row[$cols + 1] = '';                                        // Estado (vacío)
        $row[$cols + 2] = $this->minutosToHHMM($acumTardanza);      // Tardanza total
        $row[$cols + 3] = $this->decimalToHHMM($acumExtra25);       // H. Extra 25%
        $row[$cols + 4] = $this->decimalToHHMM($acumExtra35);       // H. Extra 35%
        $row[$cols + 5] = '';                                        // Observación

        return $row;
    }

    public function title(): string
    {
        return 'Registro SUNAFIL';
    }

    public function headings(): array
    {
        $headers = [
            'N°',
            'Apellidos y Nombres',
            'DNI / CE',
            'Área',
            'Sede',
            'Fecha',
            'Día',
            'Hora Ingreso',
            'Hora Salida',
        ];

        if ($this->incluyeRefrigerio) {
            $headers[] = 'Inicio Refrigerio';
            $headers[] = 'Fin Refrigerio';
        }

        $headers = array_merge($headers, [
            'Horas Trabajadas',
            'Estado',
            'Tardanza (hh:mm)',
            'H. Extra 25%',
            'H. Extra 35%',
            'Observación',
        ]);

        if ($this->incluyeGeolocalizacion) {
            $headers[] = 'Latitud (marcación remota)';
            $headers[] = 'Longitud (marcación remota)';
        }

        return $headers;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->headings()));

        $sheet->insertNewRowBefore(1, 3);

        $sheet->setCellValue('A1', $this->meta['empresa_razon_social']);
        $locationNombre = $this->meta['location'] ? $this->meta['location']->nombre : 'Todas las sedes';
        $sheet->setCellValue('A2', 'Sede: ' . $locationNombre);
        $sheet->setCellValue('A3', 'Período: ' . $this->meta['fecha_inicio'] . ' al ' . $this->meta['fecha_fin']);

        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->mergeCells('A3:' . $lastCol . '3');

        $styles = [
            1 => [
                'font'      => ['bold' => true, 'size' => 13],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font'      => ['size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            3 => [
                'font'      => ['bold' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F4FD']],
            ],
            4 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];

        // Estilar filas de subtotal (offset +4: 3 filas de header insertadas + 1 fila de headings)
        foreach ($this->subtotalRows as $rowIndex) {
            $excelRow          = $rowIndex + 4;
            $styles[$excelRow] = [
                'font' => ['bold' => true, 'color' => ['rgb' => '1E3A5F']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1E8FF']],
            ];
        }

        return $styles;
    }
}

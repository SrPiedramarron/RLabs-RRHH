<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SunafilExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize,
    WithTitle
{
    private Collection $sorted;

    public function __construct(
        private Collection $records,
        private array $meta,
        private bool $incluyeRefrigerio = false
    ) {
        // Ordenar por apellidos, nombres, fecha
        $this->sorted = $records->sortBy([
            fn($a, $b) => strcmp(
                ($a->employee->apellidos ?? '') . ($a->employee->nombres ?? ''),
                ($b->employee->apellidos ?? '') . ($b->employee->nombres ?? '')
            ),
            fn($a, $b) => strcmp($a->fecha, $b->fecha),
        ])->values();
    }

    private function decimalToHHMM(?float $decimal): string
    {
        if (!$decimal || $decimal <= 0) return '—';
        $total = (int) round($decimal * 60);
        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    private function calcularHorasTrabajadas($record): string
    {
        if (!$record->hora_entrada || !$record->hora_salida) return '—';
        if (in_array($record->estado, ['ausente', 'descanso', 'feriado'])) return '—';

        $schedule  = $record->employee->schedule;
        if (!$schedule) return '—';

        $fecha     = Carbon::parse($record->fecha)->toDateString();
        $entradaReal    = Carbon::parse($record->hora_entrada);
        $salidaReal     = Carbon::parse($record->hora_salida);
        $entradaProg    = Carbon::parse($fecha . ' ' . $schedule->hora_entrada);
        $salidaProg     = Carbon::parse($fecha . ' ' . $schedule->hora_salida);

        // Entrada efectiva: la más tardía entre entrada real y programada
        // (si llegó antes, no se cuenta el tiempo extra al inicio)
        $entradaEfectiva = $entradaReal->gt($entradaProg) ? $entradaReal : $entradaProg;

        // Salida efectiva: la más temprana entre salida real y programada
        // (horas extra se calculan aparte)
        $salidaEfectiva = $salidaReal->lt($salidaProg) ? $salidaReal : $salidaProg;

        if ($salidaEfectiva->lte($entradaEfectiva)) return '—';

        $minutos = (int) $entradaEfectiva->diffInMinutes($salidaEfectiva, true);

        // Descontar refrigerio
        if ($record->inicio_refrigerio && $record->fin_refrigerio) {
            $iniRef = Carbon::parse($record->inicio_refrigerio);
            $finRef = Carbon::parse($record->fin_refrigerio);
            $minutos -= (int) $iniRef->diffInMinutes($finRef, true);
        } elseif ($schedule->refrigerio_inicio && $schedule->refrigerio_fin) {
            $refInicio = Carbon::parse($fecha . ' ' . $schedule->refrigerio_inicio);
            $refFin    = Carbon::parse($fecha . ' ' . $schedule->refrigerio_fin);
            if ($entradaEfectiva->lt($refFin) && $salidaEfectiva->gt($refInicio)) {
                $minutos -= (int) $refInicio->diffInMinutes($refFin, true);
            }
        }

        $minutos = max(0, $minutos);
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    public function collection(): Collection
    {
        return $this->sorted;
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
            'Tardanza (min)',
            'H. Extra 25%',
            'H. Extra 35%',
            'Observación',
        ]);

        return $headers;
    }

    public function map($record): array
    {
        static $contador = 0;
        $contador++;

        $estadoMap = [
            'presente' => 'Presente',
            'tarde'    => 'Tarde',
            'ausente'  => 'Ausente',
            'descanso' => 'Descanso',
            'feriado'  => 'Feriado',
        ];

        $row = [
            $contador,
            trim(($record->employee->apellidos ?? '') . ', ' . ($record->employee->nombres ?? '')),
            $record->employee->dni ?? '',
            $record->employee->department->nombre ?? '—',
            $record->employee->location->nombre ?? '—',
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
            $this->calcularHorasTrabajadas($record),
            $estadoMap[$record->estado] ?? ucfirst($record->estado ?? '—'),
            $record->minutos_tarde > 0 ? $record->minutos_tarde : '—',
            $this->decimalToHHMM($record->horas_extra_diurnas),
            $this->decimalToHHMM($record->horas_extra_nocturnas),
            $record->observacion ?? '',
        ]);

        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = $this->incluyeRefrigerio ? 'O' : 'M';

        $sheet->insertNewRowBefore(1, 3);

        $sheet->setCellValue('A1', $this->meta['empresa_razon_social']);
        $locationNombre = $this->meta['location'] ? $this->meta['location']->nombre : 'Todas las sedes';
        $sheet->setCellValue('A2', 'Sede: ' . $locationNombre);
        $sheet->setCellValue('A3', 'Período: ' . $this->meta['fecha_inicio'] . ' al ' . $this->meta['fecha_fin']);

        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->mergeCells('A3:' . $lastCol . '3');

        return [
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
    }
}

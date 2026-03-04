<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
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
    public function __construct(
        private Collection $records,
        private array $meta,
        private bool $incluyeRefrigerio = false
    ) {}

    public function collection(): Collection
    {
        return $this->records;
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
            'observacion',
        ]);

        return $headers;
    }

    public function map($record): array
    {
        static $contador = 0;
        $contador++;

        $horasTrabajadas = '—';
        if ($record->hora_entrada && $record->hora_salida) {
            $entrada  = \Carbon\Carbon::parse($record->fecha . ' ' . $record->hora_entrada);
            $salida   = \Carbon\Carbon::parse($record->fecha . ' ' . $record->hora_salida);
            $minutos  = $salida->diffInMinutes($entrada);

            // Descontar refrigerio si aplica
            if ($this->incluyeRefrigerio && $record->inicio_refrigerio && $record->fin_refrigerio) {
                $iniRef = \Carbon\Carbon::parse($record->fecha . ' ' . $record->inicio_refrigerio);
                $finRef = \Carbon\Carbon::parse($record->fecha . ' ' . $record->fin_refrigerio);
                $minutos -= $finRef->diffInMinutes($iniRef);
            }

            $h = intdiv($minutos, 60);
            $m = $minutos % 60;
            $horasTrabajadas = sprintf('%02d:%02d', $h, $m);
        }

        $row = [
            $contador,
            trim(($record->employee->apellidos ?? '') . ', ' . ($record->employee->nombres ?? '')),
            $record->employee->dni ?? '',
            $record->employee->department->nombre ?? '—',
            $record->employee->location->nombre ?? '—',
            \Carbon\Carbon::parse($record->fecha)->format('d/m/Y'),
            \Carbon\Carbon::parse($record->fecha)->locale('es')->isoFormat('dddd'),
            $record->hora_entrada ?? '—',
            $record->hora_salida ?? '—',
        ];

        if ($this->incluyeRefrigerio) {
            $row[] = $record->inicio_refrigerio ?? '—';
            $row[] = $record->fin_refrigerio ?? '—';
        }

        $row = array_merge($row, [
            $horasTrabajadas,
            ucfirst($record->estado ?? ''),
            $record->minutos_tarde > 0 ? $record->minutos_tarde : '—',
            $record->observacion ?? '',
        ]);

        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = $this->incluyeRefrigerio ? 'O' : 'M';

        // Fila de título de empresa (antes de los encabezados)
        $sheet->insertNewRowBefore(1, 3);

        $sheet->setCellValue('A1', $this->meta['empresa_razon_social']);
        $sheet->setCellValue('A2', 'RUC: ' . $this->meta['empresa_ruc'] . ' | ' . $this->meta['empresa_direccion']);
        $sheet->setCellValue('A3',
            'REGISTRO DE CONTROL DE ASISTENCIA | Período: ' .
            $this->meta['fecha_inicio'] . ' al ' . $this->meta['fecha_fin'] .
            ' | Filtro: ' . $this->meta['filtro_nombre']
        );

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
            4 => [ // fila de encabezados
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}

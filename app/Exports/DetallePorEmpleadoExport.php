<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DetallePorEmpleadoExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private Collection $records) {}

    public function collection(): Collection
    {
        return $this->records->map(fn($r) => [
            strtoupper($r->employee->nombre_completo ?? '—'),
            $r->employee->dni ?? '—',
            $r->employee->location->nombre ?? '—',
            $r->employee->department->nombre ?? '—',
            $r->dias_tarde,
            $r->dias_puntual,
            $r->dias_ausente,
            $r->total_dias,
            $r->total_minutos > 0 ? $r->total_minutos . ' min' : '—',
            $r->total_dias ? round(($r->dias_puntual / $r->total_dias) * 100) . '%' : '—',
        ]);
    }

    public function headings(): array
    {
        return ['EMPLEADO', 'DNI', 'SEDE', 'ÁREA', 'DÍAS TARDE', 'DÍAS PUNTUAL', 'AUSENCIAS', 'TOTAL DÍAS', 'MIN. ACUM. TARDE', '% PUNTUALIDAD'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C0392B']], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function title(): string { return 'Detalle por Empleado'; }
}

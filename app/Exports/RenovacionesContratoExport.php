<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RenovacionesContratoExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private Collection $records) {}

    public function collection(): Collection
    {
        return $this->records->map(fn ($r) => [
            strtoupper($r->employee->nombre_completo ?? '—'),
            $r->employee->dni ?? '—',
            $r->employee->cargo ?? '—',
            $r->numero_renovacion,
            $r->fecha_fin_anterior?->format('d/m/Y') ?? '—',
            $r->fecha_fin_nueva->format('d/m/Y'),
            $r->observacion ?? '—',
            $r->renovadoPor->name ?? '—',
            $r->renovado_at?->format('d/m/Y H:i') ?? '—',
        ]);
    }

    public function headings(): array
    {
        return ['TRABAJADOR', 'DNI', 'CARGO', 'N° RENOVACIÓN', 'FIN ANTERIOR', 'FIN NUEVO', 'OBSERVACIÓN', 'RENOVADO POR', 'FECHA DE RENOVACIÓN'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2C3E50']], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function title(): string { return 'Renovaciones de Contrato'; }
}

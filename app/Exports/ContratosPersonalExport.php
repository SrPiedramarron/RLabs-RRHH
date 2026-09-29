<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ContratosPersonalExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private Collection $empleados) {}

    public function collection(): Collection
    {
        return $this->empleados->map(fn ($e) => [
            strtoupper($e->apellidos . ', ' . $e->nombres),
            $e->dni,
            $e->department?->nombre ?? '—',
            $e->cargo ?? '—',
            (float) $e->sueldo_base,
            $e->fecha_ingreso?->format('d/m/Y') ?? '—',
            $e->fecha_fin_contrato?->format('d/m/Y') ?? '—',
            $e->tipo_contrato_label,
        ]);
    }

    public function headings(): array
    {
        return [
            'TRABAJADOR', 'DNI', 'ÁREA', 'PUESTO', 'REMUNERACIÓN',
            'FECHA DE INICIO', 'FECHA DE FIN DE CONTRATO', 'TIPO DE CONTRATO',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2C3E50']], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function title(): string { return 'Contratos del Personal'; }
}

<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BoletasPendientesExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private Collection $liquidaciones, private string $mesNombre) {}

    public function collection(): Collection
    {
        return $this->liquidaciones->map(fn ($l) => [
            strtoupper($l->apellidos . ', ' . $l->nombres),
            $l->dni,
            $l->cargo ?? '—',
            $l->mes_nombre,
            'PENDIENTE DE FIRMA',
        ]);
    }

    public function headings(): array
    {
        return ['TRABAJADOR', 'DNI', 'CARGO', 'PERIODO', 'ESTADO'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DC2626']], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function title(): string { return 'Boletas Pendientes ' . $this->mesNombre; }
}

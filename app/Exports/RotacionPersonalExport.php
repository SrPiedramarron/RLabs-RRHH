<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RotacionPersonalExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private Collection $altas, private Collection $bajas, private string $periodoNombre) {}

    public function collection(): Collection
    {
        $filasAltas = $this->altas->map(fn ($e) => [
            'ALTA',
            strtoupper($e->apellidos . ', ' . $e->nombres),
            $e->dni,
            $e->department?->nombre ?? '—',
            $e->cargo ?? '—',
            $e->fecha_ingreso->format('d/m/Y'),
            $e->tipo_contrato_label,
        ]);

        $filasBajas = $this->bajas->map(fn ($e) => [
            'BAJA',
            strtoupper($e->apellidos . ', ' . $e->nombres),
            $e->dni,
            $e->department?->nombre ?? '—',
            $e->cargo ?? '—',
            $e->fecha_cese->format('d/m/Y'),
            $e->tipo_contrato_label,
        ]);

        return $filasAltas->concat($filasBajas);
    }

    public function headings(): array
    {
        return ['MOVIMIENTO', 'TRABAJADOR', 'DNI', 'ÁREA', 'PUESTO', 'FECHA', 'TIPO DE CONTRATO'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0891B2']], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function title(): string { return 'Rotación ' . $this->periodoNombre; }
}

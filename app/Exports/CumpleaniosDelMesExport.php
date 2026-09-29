<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CumpleaniosDelMesExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private Collection $empleados, private string $mesNombre, private int $anio) {}

    public function collection(): Collection
    {
        return $this->empleados
            ->sortBy(fn ($e) => $e->fecha_nacimiento->format('d'))
            ->values()
            ->map(fn ($e) => [
                strtoupper($e->apellidos . ', ' . $e->nombres),
                $e->dni,
                $e->department?->nombre ?? '—',
                $e->cargo ?? '—',
                $e->fecha_nacimiento->format('d/m'),
                ($this->anio - $e->fecha_nacimiento->year) . ' años',
            ]);
    }

    public function headings(): array
    {
        return ['TRABAJADOR', 'DNI', 'ÁREA', 'PUESTO', 'CUMPLE EL', 'EDAD QUE CUMPLE'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '7C3AED']], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function title(): string { return 'Cumpleaños ' . $this->mesNombre; }
}

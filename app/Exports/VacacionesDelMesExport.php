<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VacacionesDelMesExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private Collection $historial, private string $periodoNombre) {}

    public function collection(): Collection
    {
        return $this->historial->map(fn ($h) => [
            strtoupper($h->employee->apellidos . ', ' . $h->employee->nombres),
            $h->employee->dni,
            $h->employee->cargo ?? '—',
            $h->employee->department?->nombre ?? '—',
            $h->fecha_inicio->format('d/m/Y'),
            $h->fecha_fin->format('d/m/Y'),
            $h->dias,
            $h->observacion ?? '—',
            $h->registradoPor?->name ?? '—',
            $h->created_at->format('d/m/Y H:i'),
        ]);
    }

    public function headings(): array
    {
        return [
            'TRABAJADOR', 'DNI', 'CARGO', 'ÁREA',
            'FECHA INICIO', 'FECHA FIN', 'DÍAS', 'OBSERVACIÓN',
            'REGISTRADO POR', 'FECHA DE REGISTRO',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2C3E50']], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function title(): string { return 'Vacaciones ' . $this->periodoNombre; }
}

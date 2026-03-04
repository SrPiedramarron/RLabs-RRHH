<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SinMarcacionSalidaExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private Collection $records) {}

    public function collection(): Collection
    {
        return $this->records->map(fn($r) => [
            strtoupper($r->employee->nombre_completo ?? '—'),
            $r->employee->dni ?? '—',
            $r->location->nombre ?? '—',
            $r->fecha->format('d/m/Y'),
            \Carbon\Carbon::parse($r->hora_entrada)->format('H:i'),
            'No registró',
        ]);
    }

    public function headings(): array
    {
        return ['EMPLEADO', 'DNI', 'SEDE', 'FECHA', 'HORA ENTRADA', 'HORA SALIDA'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C0392B']], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function title(): string { return 'Sin Marcación de Salida'; }
}

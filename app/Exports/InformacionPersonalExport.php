<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InformacionPersonalExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private Collection $empleados) {}

    public function collection(): Collection
    {
        return $this->empleados->map(fn ($e) => [
            strtoupper($e->nombres),
            strtoupper($e->apellidos),
            $e->dni,
            $e->fecha_ingreso?->format('d/m/Y') ?? '—',
            $e->fecha_nacimiento?->format('d/m/Y') ?? '—',
            $e->cargo ?? '—',
            $e->department?->nombre ?? '—',
            $e->celular ?? '—',
            $e->correo_corporativo ?? '—',
            $e->correo_personal ?? '—',
            $e->centro_costos ?? '—',
        ]);
    }

    public function headings(): array
    {
        return [
            'NOMBRES', 'APELLIDOS', 'DNI', 'FECHA DE INGRESO', 'FECHA DE NACIMIENTO',
            'PUESTO', 'ÁREA', 'CELULAR', 'CORREO CORPORATIVO', 'CORREO PERSONAL', 'CENTRO DE COSTOS',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2C3E50']], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function title(): string { return 'Información del Personal'; }
}

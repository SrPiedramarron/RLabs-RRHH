<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CuentasBancariasExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private Collection $empleados) {}

    public function collection(): Collection
    {
        return $this->empleados->map(fn ($e) => [
            strtoupper($e->apellidos . ', ' . $e->nombres),
            $e->dni,
            $e->cargo ?? '—',
            $e->banco ?? '—',
            $e->numero_cuenta ?? '—',
            $e->cci ?? '—',
            $e->banco_cts ?? '—',
            $e->numero_cuenta_cts ?? '—',
            $e->cci_cts ?? '—',
        ]);
    }

    public function headings(): array
    {
        return [
            'TRABAJADOR', 'DNI', 'CARGO',
            'BANCO (REMUNERACIÓN)', 'N° CUENTA', 'CCI',
            'BANCO (CTS)', 'N° CUENTA CTS', 'CCI CTS',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2C3E50']], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function title(): string { return 'Cuentas Bancarias'; }
}

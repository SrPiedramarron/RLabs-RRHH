<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Resumen de pago de honorarios (locadores) de una quincena. */
class HonorariosPagoExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle, WithStrictNullComparison
{
    public function __construct(private Collection $pagos, private string $titulo) {}

    public function collection(): Collection
    {
        return $this->pagos->map(fn ($p) => [
            strtoupper($p->employee->apellidos . ', ' . $p->employee->nombres),
            $p->employee->dni,
            $p->employee->ruc_locador ?: '—',
            $p->employee->banco ?: '—',
            $p->employee->numero_cuenta ?: '—',
            $p->employee->cci ?: '—',
            (float) $p->honorario_bruto,
            (float) $p->retencion_4ta,
            (float) $p->neto_pagar,
            $p->suspension_4ta ? 'SÍ' : 'NO',
            $p->recibo_numero ?: 'Falta',
            $p->recibo_fecha?->format('d/m/Y') ?? '—',
        ]);
    }

    public function headings(): array
    {
        return ['LOCADOR', 'DNI', 'RUC', 'BANCO', 'N.º DE CUENTA', 'CCI', 'HONORARIO', 'RETENCIÓN 4TA', 'NETO A PAGAR', 'SUSPENSIÓN 4TA', 'RECIBO N.º', 'FECHA DEL RECIBO'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2C3E50']], 'alignment' => ['horizontal' => 'center']]];
    }

    public function title(): string
    {
        return substr($this->titulo, 0, 31);
    }
}

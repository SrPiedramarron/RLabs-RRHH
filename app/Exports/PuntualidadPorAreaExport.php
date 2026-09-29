<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PuntualidadPorAreaExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    /** @param Collection $filas cada fila: ['area', 'dias_trabajados', 'dias_tarde', 'total_minutos_tarde'] */
    public function __construct(private Collection $filas, private string $periodoNombre) {}

    public function collection(): Collection
    {
        return $this->filas
            ->sortByDesc(fn ($f) => $f['dias_trabajados'] > 0 ? $f['dias_tarde'] / $f['dias_trabajados'] : 0)
            ->values()
            ->map(function ($f) {
                $porcentajePuntual = $f['dias_trabajados'] > 0
                    ? round((($f['dias_trabajados'] - $f['dias_tarde']) / $f['dias_trabajados']) * 100, 1)
                    : 0;

                return [
                    $f['area'],
                    $f['dias_trabajados'],
                    $f['dias_tarde'],
                    $porcentajePuntual . '%',
                    sprintf('%02d:%02d', intdiv($f['total_minutos_tarde'], 60), $f['total_minutos_tarde'] % 60),
                ];
            });
    }

    public function headings(): array
    {
        return ['ÁREA', 'DÍAS TRABAJADOS', 'DÍAS CON TARDANZA', '% PUNTUALIDAD', 'TOTAL MINUTOS TARDE'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2C3E50']], 'alignment' => ['horizontal' => 'center']],
        ];
    }

    public function title(): string { return 'Puntualidad por Área ' . $this->periodoNombre; }
}

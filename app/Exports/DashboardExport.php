<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Excel de un dashboard: una hoja de resumen con los indicadores y una hoja por cada tabla. */
class DashboardExport implements WithMultipleSheets
{
    public function __construct(private array $reporte) {}

    public function sheets(): array
    {
        $r = $this->reporte;

        $filasResumen = [[$r['titulo']], [$r['subtitulo']], [$r['nota'] ?? ''], []];
        foreach ($r['kpis'] as $k) {
            $filasResumen[] = [$k[0], $k[1]];
        }

        $hojas = [new DashboardHoja('Resumen', $filasResumen, null, true)];
        foreach ($r['hojas'] as $h) {
            $hojas[] = new DashboardHoja(mb_substr($h['titulo'], 0, 31), $h['filas'], $h['columnas']);
        }

        return $hojas;
    }
}

class DashboardHoja implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private string $titulo,
        private array $filas,
        private ?array $columnas = null,
        private bool $esResumen = false,
    ) {}

    public function array(): array
    {
        return $this->columnas ? array_merge([$this->columnas], $this->filas) : $this->filas;
    }

    public function title(): string { return $this->titulo; }

    public function styles(Worksheet $sheet): array
    {
        if ($this->esResumen) {
            return [1 => ['font' => ['bold' => true, 'size' => 14]]];
        }

        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E3A5F']], 'alignment' => ['horizontal' => 'center']]];
    }
}

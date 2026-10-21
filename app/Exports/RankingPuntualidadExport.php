<?php

namespace App\Exports;

use App\Support\EstadisticasAsistencia;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Un libro con los cuatro rankings: general, por sede, por área y áreas por sede. */
class RankingPuntualidadExport implements WithMultipleSheets
{
    /** @param array<string, Collection> $porVista */
    public function __construct(private array $porVista) {}

    public function sheets(): array
    {
        $titulos = [
            'general'   => 'General',
            'sedes'     => 'Por sede',
            'areas'     => 'Por área',
            'sede_area' => 'Áreas por sede',
        ];

        return collect($this->porVista)
            ->map(fn ($filas, $vista) => new RankingPuntualidadHoja($vista, $titulos[$vista], $filas))
            ->values()->all();
    }
}

class RankingPuntualidadHoja implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private string $vista, private string $titulo, private Collection $filas) {}

    public function headings(): array
    {
        return array_merge(['#'], match ($this->vista) {
            'general'   => ['TRABAJADOR', 'SEDE', 'ÁREA'],
            'sedes'     => ['SEDE'],
            'areas'     => ['ÁREA'],
            default     => ['SEDE', 'ÁREA'],
        }, $this->vista === 'general' ? [] : ['TRABAJADORES'],
            ['DÍAS TARDE', 'DÍAS PUNTUAL', '% PUNTUALIDAD']);
    }

    public function collection(): Collection
    {
        return $this->filas->map(fn ($r) => array_merge([$r->posicion], match ($this->vista) {
            'general'   => [strtoupper($r->empleado), $r->sede, $r->area],
            'sedes'     => [$r->sede],
            'areas'     => [$r->area],
            default     => [$r->sede, $r->area],
        }, $this->vista === 'general' ? [] : [$r->n_empleados], [
            (int) $r->dias_tarde,
            (int) $r->dias_puntual,
            $r->pct === null ? '—' : number_format((float) $r->pct, 1) . '%',
        ]));
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C0392B']], 'alignment' => ['horizontal' => 'center']]];
    }

    public function title(): string { return $this->titulo; }
}

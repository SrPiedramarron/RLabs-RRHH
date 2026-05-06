<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportGenerator
{
    /**
     * Genera PDF SUNAFIL con soporte de filtros por área/sede y refrigerio.
     *
     * @param Collection $records  Colección de AttendanceRecord (ya filtrada)
     * @param array      $options  [fecha_inicio, fecha_fin, incluye_refrigerio, location_id, department_id]
     */
    public function generatePdfSunafil(Collection $records, array $options = []): StreamedResponse
    {
        $meta = $this->buildMeta($options);

        $pdf = Pdf::loadView('reports.sunafil-pdf', [
            'records'           => $records,
            'meta'              => $meta,
            'incluye_refrigerio' => $options['incluye_refrigerio'] ?? false,
        ])
        ->setPaper('A4', 'landscape')
        ->setOptions([
            'defaultFont'   => 'sans-serif',
            'isHtml5ParserEnabled' => true,
        ]);

        $filename = 'SUNAFIL_' . $meta['periodo_slug'] . '_' . $meta['filtro_slug'] . '.pdf';

        return response()->streamDownload(
            fn() => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Genera Excel SUNAFIL con soporte de filtros y columnas de refrigerio.
     */
    public function generateExcelSunafil(Collection $records, array $options = []): BinaryFileResponse
    {
        $meta = $this->buildMeta($options);
        $incluye_refrigerio = $options['incluye_refrigerio'] ?? false;

        $export = new \App\Exports\SunafilExport($records, $meta, $incluye_refrigerio);

        $filename = 'SUNAFIL_' . $meta['periodo_slug'] . '_' . $meta['filtro_slug'] . '.xlsx';

        return Excel::download($export, $filename);
    }

    // ─── Helpers privados ──────────────────────────────────────────────────

    private function buildMeta(array $options): array
    {
        $user    = Auth::user();
        $company = $user->company_id ? Company::find($user->company_id) : Company::first();

        $location   = isset($options['location_id']) ? Location::find($options['location_id']) : null;
        $department = isset($options['department_id']) ? Department::find($options['department_id']) : null;

        $filtroNombre = collect([
            $location?->nombre,
            $department?->nombre,
        ])->filter()->implode(' - ');

        $filtroSlug = collect([
            $location ? 'SEDE-' . strtoupper(str_replace(' ', '_', $location->nombre)) : 'TODAS_SEDES',
            $department ? 'AREA-' . strtoupper(str_replace(' ', '_', $department->nombre)) : 'TODAS_AREAS',
        ])->implode('_');

        $desde = \Carbon\Carbon::parse($options['fecha_inicio']);
        $hasta = \Carbon\Carbon::parse($options['fecha_fin']);

        return [
            'empresa_razon_social' => $company ? $company->ruc . ' - ' . $company->razon_social : 'EMPRESA',
            'empresa_ruc'          => $company?->ruc ?? '',
            'empresa_direccion'    => $company?->direccion ?? '',
            'fecha_inicio'         => $desde->format('d/m/Y'),
            'fecha_fin'            => $hasta->format('d/m/Y'),
            'fecha_inicio_raw'     => $desde->toDateString(),
            'fecha_fin_raw'        => $hasta->toDateString(),
            'periodo_slug'         => $desde->format('Ym') . ($desde->format('Ym') !== $hasta->format('Ym') ? '_' . $hasta->format('Ym') : ''),
            'filtro_nombre'        => $filtroNombre ?: 'Todos',
            'filtro_slug'          => $filtroSlug,
            'location'             => $location,
            'department'           => $department,
            'generado_en'          => now()->format('d/m/Y H:i'),
            'generado_por'         => $user->name,
        ];
    }
}

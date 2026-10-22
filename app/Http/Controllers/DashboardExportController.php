<?php

namespace App\Http\Controllers;

use App\Exports\DashboardExport;
use App\Helpers\CompanyContext;
use App\Models\Company;
use App\Services\DashboardExportService;
use App\Services\PuntualidadDashboardService;
use App\Services\VacacionesPorPeriodoService;
use App\Support\EstadisticasAsistencia;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/** Exportación a Excel y PDF de los dashboards, con los mismos filtros y datos que ven en pantalla. */
class DashboardExportController extends Controller
{
    public function __construct(private DashboardExportService $export) {}

    private function empresa(): string
    {
        $id = CompanyContext::get();

        return $id ? (string) Company::find($id)?->razon_social : 'Todas las empresas';
    }

    private function entregar(array $reporte, string $formato, string $archivo)
    {
        if ($formato === 'pdf') {
            return Pdf::loadView('reportes.dashboard-tablas', ['r' => $reporte])
                ->setPaper('a4', 'landscape')
                ->download($archivo . '.pdf');
        }

        return Excel::download(new DashboardExport($reporte), $archivo . '.xlsx');
    }

    public function puntualidad(Request $request, string $formato)
    {
        [$desde, $hasta] = EstadisticasAsistencia::rangoDeRequest($request);
        $companyId = CompanyContext::get();
        $sede = (int) $request->query('sede') ?: null;
        $area = (int) $request->query('area') ?: null;

        $data = app(PuntualidadDashboardService::class)->build($companyId, $desde, $hasta, $sede, $area);

        $filtros = array_filter([
            $sede ? 'Sede: ' . DB::table('locations')->where('id', $sede)->value('nombre') : null,
            $area ? 'Área: ' . DB::table('departments')->where('id', $area)->value('nombre') : null,
        ]);

        $reporte = $this->export->puntualidad($data, $this->empresa(), implode(' · ', $filtros));

        return $this->entregar($reporte, $formato, 'puntualidad_' . $desde->format('Ymd') . '_' . $hasta->format('Ymd'));
    }

    public function vacaciones(Request $request, string $formato)
    {
        $corte = Carbon::parse($request->query('fecha_corte', now()->toDateString()));
        $data  = app(VacacionesPorPeriodoService::class)->buildDashboardData(CompanyContext::get(), $corte);

        $reporte = $this->export->vacaciones($data, $this->empresa(), $corte->toDateString());

        return $this->entregar($reporte, $formato, 'vacaciones_corte_' . $corte->format('Ymd'));
    }
}

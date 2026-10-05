<?php

namespace App\Http\Controllers;

use App\Exports\AfpnetExport;
use App\Models\PlanillaLiquidacion;
use Maatwebsite\Excel\Facades\Excel;

class AfpnetExportController extends Controller
{
    public function exportar(string $periodo, int $companyId)
    {
        $liquidaciones = PlanillaLiquidacion::with(['employee', 'company'])
            ->where('periodo', $periodo)
            ->where('company_id', $companyId)
            ->where('sistema_pensiones', 'like', 'afp_%')
            ->orderBy('apellidos')
            ->get();

        abort_if($liquidaciones->isEmpty(), 404, 'No hay trabajadores con AFP en la planilla calculada de ese periodo.');

        $ruc = $liquidaciones->first()->company?->ruc ?? $companyId;

        return Excel::download(
            new AfpnetExport($liquidaciones, $periodo),
            'AFPnet_' . str_replace('-', '_', $periodo) . '_' . $ruc . '.xlsx'
        );
    }
}

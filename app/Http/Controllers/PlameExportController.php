<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\PlanillaLiquidacion;
use App\Services\BoletaPagoService;
use App\Services\PlameExportService;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlameExportController extends Controller
{
    public function exportarZip(
        string $periodo,
        int $companyId,
        PlameExportService $plame,
        BoletaPagoService $boletaService
    ): BinaryFileResponse {
        $liquidaciones = PlanillaLiquidacion::with(['employee', 'company'])
            ->where('periodo', $periodo)
            ->where('company_id', $companyId)
            ->get();

        abort_if($liquidaciones->isEmpty(), 404, 'No hay planilla calculada para este periodo. Calcula la planilla primero.');

        $company = $liquidaciones->first()->company;
        $ruc     = $company->ruc ?? null;

        abort_if(empty($ruc), 500, 'La empresa no tiene RUC registrado — es obligatorio para el nombre de archivo PLAME.');

        $inicioMes = Carbon::parse($periodo . '-01')->startOfMonth();
        $finMes    = Carbon::parse($periodo . '-01')->endOfMonth();

        $attendanceDelMes = AttendanceRecord::with('employee')
            ->whereIn('employee_id', $liquidaciones->pluck('employee_id'))
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->get();

        // Generar los 3 archivos
        $e14 = $plame->generarE14Jornada($liquidaciones);
        $e15 = $plame->generarE15DiasNoLaborados($attendanceDelMes);
        $e18 = $plame->generarE18Ingresos($liquidaciones, $boletaService);

        $nombreE14 = $plame->nombreArchivoE14($periodo, $ruc);
        $nombreE15 = $plame->nombreArchivoE15($periodo, $ruc);
        $nombreE18 = $plame->nombreArchivoE18($periodo, $ruc);

        // Armar el ZIP en un archivo temporal
        $zipPath = storage_path('app/temp/plame_' . str_replace('-', '_', $periodo) . '_' . $companyId . '.zip');

        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'No se pudo crear el archivo ZIP.');
        }

        $zip->addFromString($nombreE14, $e14);
        $zip->addFromString($nombreE15, $e15);
        $zip->addFromString($nombreE18, $e18);
        $zip->close();

        $nombreDescarga = 'PLAME_' . str_replace('-', '_', $periodo) . '_' . $ruc . '.zip';

        return response()->download($zipPath, $nombreDescarga)->deleteFileAfterSend(true);
    }
}
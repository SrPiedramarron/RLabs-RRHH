<?php

$companyId = \DB::table('companies')->first()->id;
$periodo   = now()->format('Y-m');

echo "Empresa: {$companyId} | Periodo: {$periodo}" . PHP_EOL;

$liquidaciones = \App\Models\PlanillaLiquidacion::with('employee')
    ->where('company_id', $companyId)
    ->where('periodo', $periodo)
    ->get();

echo "Liquidaciones encontradas: " . $liquidaciones->count() . PHP_EOL;

if ($liquidaciones->isEmpty()) {
    echo "No hay liquidaciones — calcula planilla primero." . PHP_EOL;
    return;
}

$plame = app(\App\Services\PlameExportService::class);
$ruc   = \DB::table('companies')->where('id', $companyId)->value('ruc') ?? '20000000001';

// E15 necesita los attendance_records del mes con motivo_suspension_plame set
$inicioMes = \Carbon\Carbon::parse($periodo . '-01')->startOfMonth();
$finMes    = \Carbon\Carbon::parse($periodo . '-01')->endOfMonth();

$attendanceDelMes = \App\Models\AttendanceRecord::with('employee')
    ->whereIn('employee_id', $liquidaciones->pluck('employee_id'))
    ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
    ->get();

echo "Registros de asistencia del mes: " . $attendanceDelMes->count() . PHP_EOL;
echo "  Con motivo_suspension_plame: " . $attendanceDelMes->whereNotNull('motivo_suspension_plame')->count() . PHP_EOL;

$e15 = $plame->generarE15DiasNoLaborados($attendanceDelMes);

file_put_contents(base_path($plame->nombreArchivoE15($periodo, $ruc)), $e15);

echo PHP_EOL . "Archivo generado: " . $plame->nombreArchivoE15($periodo, $ruc) . PHP_EOL;
echo PHP_EOL . "Contenido E15 (.snl):" . PHP_EOL;
echo $e15 === '' ? "(vacío — no hay días no laborados con motivo asignado este mes)" : $e15;
echo PHP_EOL;

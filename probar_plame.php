<?php

$companyId = \DB::table('companies')->first()->id;
$periodo   = now()->format('Y-m');

echo "Empresa: {$companyId} | Periodo: {$periodo}" . PHP_EOL;

// 1. Calcular planilla si no existe todavía para este periodo
$existe = \App\Models\PlanillaLiquidacion::where('company_id', $companyId)
    ->where('periodo', $periodo)
    ->exists();

if (!$existe) {
    echo "Calculando planilla..." . PHP_EOL;
    app(\App\Services\PlanillaService::class)->calcularPeriodo($companyId, $periodo);
} else {
    echo "Planilla ya existía para este periodo, usando la existente." . PHP_EOL;
}

$liquidaciones = \App\Models\PlanillaLiquidacion::with('employee')
    ->where('company_id', $companyId)
    ->where('periodo', $periodo)
    ->get();

echo "Liquidaciones encontradas: " . $liquidaciones->count() . PHP_EOL;

if ($liquidaciones->isEmpty()) {
    echo "No hay liquidaciones — revisa que los empleados tengan sueldo_base > 0." . PHP_EOL;
    return;
}

// 2. Generar los 3 archivos PLAME
$plame  = app(\App\Services\PlameExportService::class);
$boleta = app(\App\Services\BoletaPagoService::class);

$ruc = \DB::table('companies')->where('id', $companyId)->value('ruc') ?? '20000000001';

$e14 = $plame->generarE14Jornada($liquidaciones);
$e18 = $plame->generarE18Ingresos($liquidaciones, $boleta);

file_put_contents(base_path($plame->nombreArchivoE14($periodo, $ruc)), $e14);
file_put_contents(base_path($plame->nombreArchivoE18($periodo, $ruc)), $e18);

echo PHP_EOL . "Archivos generados en la raíz del proyecto:" . PHP_EOL;
echo "  " . $plame->nombreArchivoE14($periodo, $ruc) . PHP_EOL;
echo "  " . $plame->nombreArchivoE18($periodo, $ruc) . PHP_EOL;

echo PHP_EOL . "Primeras 5 líneas de E14 (.jor):" . PHP_EOL;
echo implode(PHP_EOL, array_slice(explode("\r\n", $e14), 0, 5)) . PHP_EOL;

echo PHP_EOL . "Primeras 10 líneas de E18 (.rem):" . PHP_EOL;
echo implode(PHP_EOL, array_slice(explode("\r\n", $e18), 0, 10)) . PHP_EOL;

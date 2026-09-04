<?php
$ernesto = \App\Models\Employee::find(37);

$retencionRealJunio = (float) \App\Models\IngresoHistorico5ta::where('employee_id', $ernesto->id)
    ->where('periodo', '2026-06')
    ->where('concepto', 'RETENCION_5TA_APLICADA')
    ->value('monto');

echo "Retención REAL aplicada en junio (del Excel): S/ " . number_format($retencionRealJunio, 2) . PHP_EOL . PHP_EOL;

$comisionesJunio = (float) \App\Models\IngresoHistorico5ta::where('employee_id', $ernesto->id)
    ->where('periodo', '2026-06')
    ->where('concepto', 'COMISIONES')
    ->value('monto');

echo "Comisiones reales de junio: S/ " . number_format($comisionesJunio, 2) . PHP_EOL . PHP_EOL;

$resultado = app(\App\Services\Renta5taCalculator::class)->calcular($ernesto, $comisionesJunio, 2026, 6);

echo "=== Nuestro cálculo (fórmula replicada) para junio ===" . PHP_EOL;
foreach ($resultado as $k => $v) {
    echo str_pad($k, 30) . ": " . (is_numeric($v) ? number_format($v, 2) : $v) . PHP_EOL;
}

echo PHP_EOL . "Diferencia (nuestro - real): S/ " . number_format($resultado['cuota_mensual'] - $retencionRealJunio, 2) . PHP_EOL;

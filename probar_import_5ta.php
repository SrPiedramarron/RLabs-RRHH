<?php
// Ajusta la ruta si el Excel está en otro lugar
$path = base_path('2DA_QUINCENA_DE_JULIO_2026.xlsx');
$companyId = \DB::table('companies')->where('ruc', '20514706302')->value('id'); // InProcess

if (!$companyId) {
    echo "No se encontró InProcess por RUC, revisa manualmente." . PHP_EOL;
    return;
}

echo "Company InProcess ID: {$companyId}" . PHP_EOL . PHP_EOL;

\DB::beginTransaction();

$service = app(\App\Services\HistoricoRentaImportService::class);

$reporteVendedores = $service->importarHoja($path, 'RTA 5TA VTAS', $companyId, 2026);
$reporteResto       = $service->importarHoja($path, 'RTA 5TA', $companyId, 2026);

foreach ([$reporteVendedores, $reporteResto] as $r) {
    echo "=== Hoja: {$r['hoja']} ===" . PHP_EOL;
    echo "Empleados en la hoja: {$r['empleados_en_hoja']}" . PHP_EOL;
    echo "Filas importadas (matcheadas): {$r['filas_importadas']}" . PHP_EOL;
    echo "Empleados SIN matchear: " . (empty($r['empleados_sin_matchear']) ? 'ninguno' : implode(', ', $r['empleados_sin_matchear'])) . PHP_EOL;
    echo PHP_EOL;
}

// Muestra un par de líneas de ejemplo para inspección visual
echo "=== Muestra de 15 líneas procesadas (hoja vendedores) ===" . PHP_EOL;
foreach (array_slice($reporteVendedores['detalle'], 0, 15) as $l) {
    echo "{$l['periodo']} | {$l['concepto']} | {$l['empleado']} | S/ {$l['monto']} | " . ($l['matched'] ? 'OK' : '¿SIN MATCH?') . PHP_EOL;
}

\DB::rollBack();
echo PHP_EOL . "(Rollback aplicado — nada se guardó todavía, esto fue solo una prueba)" . PHP_EOL;

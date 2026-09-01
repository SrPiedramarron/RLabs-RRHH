<?php
// Ajusta la ruta si el Excel está en otro lugar
$path = base_path('2DA_QUINCENA_DE_JULIO_2026.xlsx');
$companyId = \DB::table('companies')->where('ruc', '20514706302')->value('id'); // InProcess

if (!$companyId) {
    echo "No se encontró InProcess por RUC, revisa manualmente." . PHP_EOL;
    return;
}

echo "Company InProcess ID: {$companyId}" . PHP_EOL . PHP_EOL;

// COMMIT REAL — ya validado, esto sí guarda en firme.

$service = app(\App\Services\HistoricoRentaImportService::class);

// Alias para typos conocidos del Excel (confirmados con Ricardo, ago 2026):
// "Rihard Loli" en el Excel es en realidad "Richard Loli" en el sistema.
// "Aracelli Araida" en el Excel es en realidad "Aracelli Pérez" en el sistema.
$richardId  = \App\Models\Employee::where('company_id', $companyId)
    ->where('nombres', 'like', '%RICHARD%')->where('apellidos', 'like', '%LOLI%')->value('id');
$aracelliId = \App\Models\Employee::where('company_id', $companyId)
    ->where('nombres', 'like', '%ARACELLI%')->where('apellidos', 'like', '%PEREZ%')->value('id');

echo "Richard Loli encontrado con ID: " . ($richardId ?? 'NO ENCONTRADO') . PHP_EOL;
echo "Aracelli Perez encontrado con ID: " . ($aracelliId ?? 'NO ENCONTRADO') . PHP_EOL . PHP_EOL;

$aliasManual = array_filter([
    'RIHARD LOLI'      => $richardId,
    'ARACELLI ARAIDA'  => $aracelliId,
]);

$reporteVendedores = $service->importarHoja($path, 'RTA 5TA VTAS', $companyId, 2026);
$reporteResto       = $service->importarHoja($path, 'RTA 5TA', $companyId, 2026, aliasManual: $aliasManual);

foreach ([$reporteVendedores, $reporteResto] as $r) {
    echo "=== Hoja: {$r['hoja']} ===" . PHP_EOL;
    echo "Empleados en la hoja: {$r['empleados_en_hoja']}" . PHP_EOL;
    echo "Filas importadas (matcheadas): {$r['filas_importadas']}" . PHP_EOL;
    echo "Empleados SIN matchear: " . (empty($r['empleados_sin_matchear']) ? 'ninguno' : implode(', ', $r['empleados_sin_matchear'])) . PHP_EOL;
    echo "Empleados AMBIGUOS (más de un match posible): " . (empty($r['empleados_ambiguos']) ? 'ninguno' : implode(', ', $r['empleados_ambiguos'])) . PHP_EOL;
    echo PHP_EOL;
}

// Muestra un par de líneas de ejemplo para inspección visual
echo "=== Muestra de 15 líneas procesadas (hoja vendedores) ===" . PHP_EOL;
foreach (array_slice($reporteVendedores['detalle'], 0, 15) as $l) {
    echo "{$l['periodo']} | {$l['concepto']} | {$l['empleado']} | S/ {$l['monto']} | " . ($l['matched'] ? 'OK' : '¿SIN MATCH?') . PHP_EOL;
}

echo PHP_EOL . "✅ Importación guardada en firme." . PHP_EOL;

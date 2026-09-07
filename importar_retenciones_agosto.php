<?php
$companyId = \DB::table('companies')->where('ruc', '20514706302')->value('id');
$path = base_path('2DA_QUINCENA_DE_AGOSTO_2026_-_FINAL.xlsx');

if (!file_exists($path)) {
    echo "Sube el archivo a la raíz del proyecto primero." . PHP_EOL;
    return;
}

$richardId  = \App\Models\Employee::where('company_id', $companyId)
    ->where('nombres', 'like', '%RICHARD%')->where('apellidos', 'like', '%LOLI%')->value('id');
$aracelliId = \App\Models\Employee::where('company_id', $companyId)
    ->where('nombres', 'like', '%ARACELLI%')->where('apellidos', 'like', '%PEREZ%')->value('id');

$aliasManual = array_filter([
    'RIHARD LOLI'      => $richardId,
    'ARACELLI ARAIDA'  => $aracelliId,
]);

$service = app(\App\Services\HistoricoRentaImportService::class);

$rep1 = $service->importarRetenciones($path, 'RTA 5TA VTAS', $companyId, 2026);
$rep2 = $service->importarRetenciones($path, 'RTA 5TA', $companyId, 2026, aliasManual: $aliasManual);

echo "Vendedores: {$rep1['filas_importadas']} filas | Resto: {$rep2['filas_importadas']} filas" . PHP_EOL . PHP_EOL;

echo "=== Retenciones de Richard tras importar agosto ===" . PHP_EOL;
\App\Models\IngresoHistorico5ta::where('employee_id', 71)
    ->where('concepto', 'RETENCION_5TA_APLICADA')
    ->orderBy('periodo')
    ->get(['periodo', 'monto'])
    ->each(fn ($r) => print_r($r->toArray()));

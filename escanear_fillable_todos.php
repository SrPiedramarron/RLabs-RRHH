<?php
$modelos = [
    \App\Models\Employee::class,
    \App\Models\PlanillaLiquidacion::class,
    \App\Models\AttendanceRecord::class,
    \App\Models\ComisionDetalle::class,
    \App\Models\ComisionUpload::class,
    \App\Models\Schedule::class,
    \App\Models\Location::class,
    \App\Models\Company::class,
    \App\Models\AfpTasa::class,
];

foreach ($modelos as $clase) {
    if (!class_exists($clase)) {
        echo "SKIP (no existe): {$clase}" . PHP_EOL;
        continue;
    }

    $modelo   = new $clase();
    $tabla    = $modelo->getTable();
    $columnas = \Illuminate\Support\Facades\Schema::getColumnListing($tabla);
    $fillable = $modelo->getFillable();
    $guarded  = $modelo->getGuarded();

    // Columnas que normalmente no deberían estar en fillable de todos modos
    $ignorar = ['id', 'created_at', 'updated_at', 'deleted_at'];

    $faltantes = array_diff($columnas, $fillable, $ignorar);

    if (empty($fillable)) {
        echo "⚠️  {$clase}: fillable VACÍO — revisar manualmente (puede usar otro mecanismo)." . PHP_EOL;
        continue;
    }

    if (empty($faltantes)) {
        echo "✅ {$clase}: OK, todas las columnas están en fillable." . PHP_EOL;
    } else {
        echo "❌ {$clase}: FALTAN en fillable -> " . implode(', ', $faltantes) . PHP_EOL;
    }
}
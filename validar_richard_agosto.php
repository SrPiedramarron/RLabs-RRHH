<?php
$richard = \App\Models\Employee::find(71);

// Usamos sueldo real de agosto tal como en el Excel (4113 = 4000 + 113 asig fam)
$resultado = app(\App\Services\Renta5taCalculator::class)->calcularNoComisionado($richard, 4113.00, 2026, 8);

echo "=== Richard, agosto (esperado S/141.88 según Cielo) ===" . PHP_EOL;
foreach ($resultado as $k => $v) {
    echo str_pad($k, 30) . ": " . (is_numeric($v) ? number_format($v, 2) : $v) . PHP_EOL;
}

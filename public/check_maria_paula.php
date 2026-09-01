<?php
$e = \App\Models\Employee::where('company_id', 2)->where('nombres', 'like', '%MARIA PAULA%')->first();
if ($e) {
    echo "Nombres: '{$e->nombres}'" . PHP_EOL;
    echo "Apellidos: '{$e->apellidos}'" . PHP_EOL;
} else {
    echo "No se encontró ningún empleado con nombre MARIA PAULA en InProcess." . PHP_EOL;
}

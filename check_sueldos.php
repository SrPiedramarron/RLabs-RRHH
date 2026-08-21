<?php

$total = \App\Models\Employee::where('active', true)->count();
$conSueldo = \App\Models\Employee::where('active', true)->where('sueldo_base', '>', 0)->count();

echo "Empleados activos: {$total}" . PHP_EOL;
echo "Con sueldo_base > 0: {$conSueldo}" . PHP_EOL;
echo PHP_EOL;

echo "Muestra de 5 empleados:" . PHP_EOL;
\App\Models\Employee::where('active', true)->take(5)->get(['id', 'nombres', 'apellidos', 'company_id', 'sueldo_base'])
    ->each(fn($e) => print_r($e->toArray()));

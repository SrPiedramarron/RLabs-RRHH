<?php
echo "=== Buscando 'MARIA PAULA' ===" . PHP_EOL;
\App\Models\Employee::where('nombres', 'like', '%MARIA PAULA%')->get(['id','nombres','apellidos'])
    ->each(fn($e) => print_r($e->toArray()));

echo PHP_EOL . "=== Buscando 'PORTOCARRERO' ===" . PHP_EOL;
\App\Models\Employee::where('apellidos', 'like', '%PORTOCARRERO%')->get(['id','nombres','apellidos'])
    ->each(fn($e) => print_r($e->toArray()));

echo PHP_EOL . "=== Buscando 'ORTIZ' o 'CESAR PAUL' ===" . PHP_EOL;
\App\Models\Employee::where('apellidos', 'like', '%ORTIZ%')
    ->orWhere('nombres', 'like', '%CESAR PAUL%')
    ->get(['id','nombres','apellidos'])
    ->each(fn($e) => print_r($e->toArray()));

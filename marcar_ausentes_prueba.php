<?php
$empresaId = \DB::table('companies')->first()->id;
$periodo   = now()->format('Y-m');
$inicioMes = \Carbon\Carbon::parse($periodo . '-01')->startOfMonth();
$finMes    = \Carbon\Carbon::parse($periodo . '-01')->endOfMonth();

$ids = \App\Models\AttendanceRecord::whereIn('employee_id', \App\Models\Employee::where('company_id', $empresaId)->pluck('id'))
    ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
    ->limit(2)
    ->pluck('id');

echo 'Registros encontrados en el rango: ' . $ids->count() . PHP_EOL;

\App\Models\AttendanceRecord::whereIn('id', $ids)->update([
    'estado' => 'ausente',
    'motivo_suspension_plame' => '7',
]);

echo 'Marcados: ' . $ids->count();
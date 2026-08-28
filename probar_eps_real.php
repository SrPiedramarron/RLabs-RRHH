<?php
// Tomamos a Rosa (ONP, sueldo 2800) y le asignamos EPS + forzamos sueldo a 3000
// para comparar directo contra el ejemplo de RRHH.
$empleado = \App\Models\Employee::where('dni', '51678901')->first(); // Rosa

if (!$empleado) {
    echo "No se encontró el empleado de prueba (DNI 51678901)." . PHP_EOL;
    return;
}

$empleado->update([
    'sueldo_base' => 3000,
    'monto_eps_mensual_con_igv' => 268,
]);

// Borrar liquidación previa del periodo para forzar recálculo limpio
$periodo = now()->format('Y-m');
\App\Models\PlanillaLiquidacion::where('employee_id', $empleado->id)->where('periodo', $periodo)->delete();

$companyId = $empleado->company_id;
$liquidacion = app(\App\Services\PlanillaService::class)->calcularEmpleado(
    $empleado, $companyId, $periodo, (int) explode('-', $periodo)[0], (int) explode('-', $periodo)[1]
);

echo "Remuneración bruta: {$liquidacion->remuneracion_bruta}" . PHP_EOL;
echo "EsSalud empleador: {$liquidacion->essalud_empleador}" . PHP_EOL;
echo "EPS crédito: {$liquidacion->eps_credito}" . PHP_EOL;
echo "EPS aporte empresa: {$liquidacion->eps_aporte_empresa}" . PHP_EOL;
echo "EPS descuento trabajador: {$liquidacion->eps_descuento_trabajador}" . PHP_EOL;
echo "Neto a pagar: {$liquidacion->neto_pagar}" . PHP_EOL;

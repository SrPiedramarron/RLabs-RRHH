<?php
$model = new \App\Models\Employee();
$fillable = $model->getFillable();

echo "Fillable actual de Employee:" . PHP_EOL;
print_r($fillable);

echo PHP_EOL . "¿Incluye los campos de planilla?" . PHP_EOL;
foreach (['sueldo_base', 'sistema_pensiones', 'aplica_5ta_categoria', 'aplica_comision'] as $campo) {
    echo "  {$campo}: " . (in_array($campo, $fillable) ? 'SÍ está' : '❌ FALTA') . PHP_EOL;
}

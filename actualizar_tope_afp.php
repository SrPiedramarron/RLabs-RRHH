<?php
$actualizados = \App\Models\AfpTasa::whereNull('vigente_hasta')->update([
    'tope_remuneracion_asegurable' => 12672.65,
]);
echo "Tasas AFP actualizadas: {$actualizados}" . PHP_EOL;

\App\Models\AfpTasa::whereNull('vigente_hasta')->get()->each(function ($t) {
    echo "{$t->afp}: comisión {$t->comision_flujo}, prima {$t->prima_seguro}, tope {$t->tope_remuneracion_asegurable}" . PHP_EOL;
});

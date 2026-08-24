<?php
App\Models\PlanillaLiquidacion::where('periodo', '2026-08')->delete();

$companyId = DB::table('companies')->first()->id;
app(App\Services\PlanillaService::class)->calcularPeriodo($companyId, '2026-08');

$l = App\Models\PlanillaLiquidacion::where('dni', '45123456')->where('periodo', '2026-08')->first();
print_r($l->only([
    'sistema_pensiones', 'remuneracion_bruta', 'descuento_pension',
    'afp_comision_flujo', 'afp_prima_seguro', 'afp_aporte_obligatorio', 'porcentaje_pension',
]));

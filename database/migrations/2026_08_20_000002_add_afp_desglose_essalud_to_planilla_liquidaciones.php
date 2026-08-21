<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            // ── Desglose AFP (solo aplica si sistema_pensiones no es 'onp') ────
            $table->decimal('afp_comision_flujo', 10, 2)->default(0)->after('descuento_pension');
            $table->decimal('afp_prima_seguro', 10, 2)->default(0)->after('afp_comision_flujo');
            $table->decimal('afp_aporte_obligatorio', 10, 2)->default(0)->after('afp_prima_seguro');

            // ── Aportes de empleador (no descuentan al trabajador, van en la boleta informativa) ──
            $table->decimal('essalud_empleador', 10, 2)->default(0)->after('neto_pagar');
            $table->decimal('seguro_vida_empleador', 10, 2)->default(0)->after('essalud_empleador');
        });
    }

    public function down(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn([
                'afp_comision_flujo', 'afp_prima_seguro', 'afp_aporte_obligatorio',
                'essalud_empleador', 'seguro_vida_empleador',
            ]);
        });
    }
};

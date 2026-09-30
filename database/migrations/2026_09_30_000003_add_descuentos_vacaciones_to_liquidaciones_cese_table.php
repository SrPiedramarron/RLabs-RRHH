<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidaciones_cese', function (Blueprint $table) {
            if (! Schema::hasColumn('liquidaciones_cese', 'descuento_afp_vacaciones')) {
                $table->decimal('descuento_afp_vacaciones', 10, 2)->default(0)->after('indemnizacion_vacacional');
            }
            if (! Schema::hasColumn('liquidaciones_cese', 'aporte_essalud_vacaciones')) {
                $table->decimal('aporte_essalud_vacaciones', 10, 2)->default(0)->after('descuento_afp_vacaciones');
            }
            if (! Schema::hasColumn('liquidaciones_cese', 'total_vacaciones_por_pagar')) {
                $table->decimal('total_vacaciones_por_pagar', 10, 2)->default(0)->after('aporte_essalud_vacaciones');
            }
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones_cese', function (Blueprint $table) {
            $table->dropColumn(['descuento_afp_vacaciones', 'aporte_essalud_vacaciones', 'total_vacaciones_por_pagar']);
        });
    }
};

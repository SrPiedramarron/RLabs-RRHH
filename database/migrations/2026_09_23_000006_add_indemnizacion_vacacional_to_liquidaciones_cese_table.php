<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidaciones_cese', function (Blueprint $table) {
            if (! Schema::hasColumn('liquidaciones_cese', 'remuneracion_vacacional_pendiente')) {
                // Periodo vacacional COMPLETO (30 días) ya ganado pero no
                // gozado dentro del año siguiente — distinto de vacaciones
                // truncas (periodo en curso, sin completar). Confirmado por
                // RRHH set. 2026.
                $table->decimal('remuneracion_vacacional_pendiente', 10, 2)->default(0)->after('monto_vacaciones_truncas');
            }
            if (! Schema::hasColumn('liquidaciones_cese', 'indemnizacion_vacacional')) {
                $table->decimal('indemnizacion_vacacional', 10, 2)->default(0)->after('remuneracion_vacacional_pendiente');
            }
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones_cese', function (Blueprint $table) {
            $table->dropColumn(['remuneracion_vacacional_pendiente', 'indemnizacion_vacacional']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Saldo de vacaciones pendientes ("goce físico") a la fecha de
            // corte — punto de partida cargado desde el Excel manual de
            // RRHH. Desde esta fecha en adelante, el sistema calcula solo
            // (2.5 días generados por mes trabajado, menos días tomados
            // según asistencia).
            $table->decimal('saldo_vacaciones_inicial', 6, 2)->default(0)->after('bono_encargatura');
            $table->date('fecha_saldo_vacaciones')->nullable()->after('saldo_vacaciones_inicial');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['saldo_vacaciones_inicial', 'fecha_saldo_vacaciones']);
        });
    }
};

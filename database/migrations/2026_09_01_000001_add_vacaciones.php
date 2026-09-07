<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            // Código PLAME 0118. = sueldo de los días de vacaciones (que se
            // RESTA de 0121/sueldo_proporcional para no duplicar) + promedio
            // de comisiones de los últimos 6 meses × días de vacaciones.
            $table->integer('dias_vacaciones')->default(0)->after('dias_justificados');
            $table->decimal('vacaciones', 10, 2)->default(0)->after('bono_encargatura');
        });
    }

    public function down(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn(['dias_vacaciones', 'vacaciones']);
        });
    }
};

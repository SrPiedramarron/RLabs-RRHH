<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Trabajadores de jornada corta/atípica (ej. 3 h diarias a una hora
            // distinta del horario asignado): sus horas son exactamente de la
            // entrada a la salida marcadas, sin descontar refrigerio ni
            // recortar al horario.
            $table->boolean('horas_por_marcacion')->default(false)->after('compensa_horas_extras');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('horas_por_marcacion');
        });
    }
};

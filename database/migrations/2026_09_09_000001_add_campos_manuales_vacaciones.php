<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Campos MANUALES informativos — NO reemplazan el cálculo
            // automático del saldo (que sigue basado en asistencia). RRHH
            // los llena a mano como referencia visual rápida.
            $table->date('fecha_ultima_vacacion')->nullable()->after('fecha_saldo_vacaciones');
            $table->integer('dias_tomados')->default(0)->after('fecha_ultima_vacacion');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['fecha_ultima_vacacion', 'dias_tomados']);
        });
    }
};

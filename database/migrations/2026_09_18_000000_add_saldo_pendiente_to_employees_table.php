<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'saldo_pendiente')) {
                // Saldo de días acumulados que NO se tomaron en la última
                // vacación registrada — se carga hacia adelante para no
                // perderlo cuando se reinicia el conteo desde
                // fecha_ultima_vacacion. Ver VacacionesService::registrarVacacion().
                $table->decimal('saldo_pendiente', 6, 2)->default(0)->after('dias_tomados');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('saldo_pendiente');
        });
    }
};

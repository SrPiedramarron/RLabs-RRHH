<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega trazabilidad del origen de cada marcación en attendance_records:
     * si vino del reloj biométrico o de un marcado remoto.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Origen de la marcación de entrada
            $table->enum('fuente_entrada', ['biometrico', 'remoto', 'manual'])
                ->default('biometrico')
                ->after('hora_entrada')
                ->nullable();

            // Origen de la marcación de salida
            $table->enum('fuente_salida', ['biometrico', 'remoto', 'manual'])
                ->default('biometrico')
                ->after('hora_salida')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['fuente_entrada', 'fuente_salida']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Código directo de la Tabla 21 SUNAT (Tipo de Suspensión de la
            // Relación Laboral). Null = no aplica (día normal trabajado).
            // Solo se llena cuando estado = 'ausente' (justificado o no) o
            // cuando se registra explícitamente un motivo de suspensión.
            $table->string('motivo_suspension_plame', 2)->nullable()->after('justificado');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('motivo_suspension_plame');
        });
    }
};

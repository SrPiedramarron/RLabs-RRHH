<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            // Subsidios EsSalud (código PLAME 0916/0915). NO afectan la base
            // de EsSalud ni ONP — SÍ afectan la base de AFP (aporte,
            // comisión, prima). Confirmado con RRHH, ago 2026.
            $table->decimal('subsidio_enfermedad', 10, 2)->default(0)->after('adelanto');
            $table->decimal('subsidio_maternidad', 10, 2)->default(0)->after('subsidio_enfermedad');
        });
    }

    public function down(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn(['subsidio_enfermedad', 'subsidio_maternidad']);
        });
    }
};

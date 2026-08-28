<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Trabajadores afiliados a AFP ANTES de feb-2013 pagan comisión
            // sobre flujo completa vía planilla (true, default). Los
            // afiliados DESPUÉS suelen estar en "comisión mixta" — una
            // parte la cobra la AFP directo de su cuenta, no vía planilla —
            // así que este campo debe marcarse false para ellos.
            // Aporte obligatorio (10%) y prima de seguro SIEMPRE aplican
            // a todos los afiliados AFP, sin importar este switch.
            $table->boolean('aplica_comision_flujo_afp')->default(true)->after('sistema_pensiones');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('aplica_comision_flujo_afp');
        });
    }
};

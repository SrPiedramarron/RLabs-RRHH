<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('planilla_liquidaciones', 'retencion_5ta_manual')) {
                // Nullable a propósito: null = cálculo automático (por
                // proyección), cualquier valor (incluido 0) = override manual.
                // RRHH pidió esto para lo que queda de 2026, mientras el
                // sistema no tiene histórico de ingresos de ene-ago para
                // proyectar correctamente. Desde enero 2027 debería dejarse
                // en automático.
                $table->decimal('retencion_5ta_manual', 10, 2)->nullable()->after('descuento_5ta_categoria');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('retencion_5ta_manual');
        });
    }
};

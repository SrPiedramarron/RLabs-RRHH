<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'tipo_contrato')) {
                $table->enum('tipo_contrato', [
                    'indeterminado',
                    'inicio_incremento_actividad',
                    'necesidad_mercado',
                    'obra_servicio_especifico',
                ])->nullable()->after('fecha_fin_contrato');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('tipo_contrato');
        });
    }
};

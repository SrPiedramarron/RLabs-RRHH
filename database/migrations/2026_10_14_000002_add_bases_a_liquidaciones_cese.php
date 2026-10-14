<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bases de cada cálculo, para poder mostrarlas en el documento de
        // liquidación (remuneración computable, 1/6 de gratificación, etc.).
        Schema::table('liquidaciones_cese', function (Blueprint $table) {
            $table->decimal('base_vacacional', 10, 2)->nullable();
            $table->decimal('meses_vacaciones_truncas', 5, 2)->nullable();
            $table->decimal('remuneracion_computable_cts', 10, 2)->nullable();
            $table->decimal('sexto_gratificacion_cts', 10, 2)->nullable();
            $table->decimal('remuneracion_computable_gratificacion', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones_cese', function (Blueprint $table) {
            $table->dropColumn([
                'base_vacacional', 'meses_vacaciones_truncas', 'remuneracion_computable_cts',
                'sexto_gratificacion_cts', 'remuneracion_computable_gratificacion',
            ]);
        });
    }
};

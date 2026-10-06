<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Montos mensuales pagados fuera del sistema (comisiones, horas extra,
        // bonos regulares) de meses sin liquidación en el sistema. El cálculo
        // de CTS los usa como respaldo cuando la liquidación del mes no los trae.
        Schema::create('cts_insumos_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('periodo', 7); // YYYY-MM
            $table->enum('concepto', ['comisiones', 'horas_extra', 'bono']);
            $table->decimal('monto', 10, 2);
            $table->string('nota', 150)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'periodo', 'concepto']);
        });

        Schema::table('cts_depositos', function (Blueprint $table) {
            $table->unsignedTinyInteger('meses_con_bonos')->default(0)->after('promedio_horas_extra');
            $table->decimal('promedio_bonos', 10, 2)->default(0)->after('meses_con_bonos');
        });
    }

    public function down(): void
    {
        Schema::table('cts_depositos', function (Blueprint $table) {
            $table->dropColumn(['meses_con_bonos', 'promedio_bonos']);
        });
        Schema::dropIfExists('cts_insumos_historicos');
    }
};

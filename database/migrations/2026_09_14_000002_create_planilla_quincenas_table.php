<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilla_quincenas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('periodo', 7); // 'YYYY-MM' — adelanto del día 15 de ese mes
            $table->string('mes_nombre');

            $table->string('nombres');
            $table->string('apellidos');
            $table->string('dni');
            $table->string('cargo')->nullable();

            $table->decimal('sueldo_base', 10, 2);
            $table->decimal('base_quincena', 10, 2); // sueldo_base / 2

            $table->string('sistema_pensiones');
            $table->decimal('porcentaje_pension', 6, 4)->default(0);
            $table->decimal('descuento_pension', 10, 2)->default(0);
            $table->decimal('afp_comision_flujo', 10, 2)->default(0);
            $table->decimal('afp_prima_seguro', 10, 2)->default(0);
            $table->decimal('afp_aporte_obligatorio', 10, 2)->default(0);

            $table->boolean('aplica_5ta_categoria')->default(false);
            $table->decimal('descuento_5ta_categoria', 10, 2)->default(0);

            // Lo que efectivamente se le deposita al trabajador el día 15.
            $table->decimal('neto_pagar', 10, 2)->default(0);

            $table->unsignedBigInteger('calculado_por')->nullable();
            $table->timestamp('calculado_at')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'periodo'], 'planilla_quincenas_unico');
        });

        // La liquidación mensual (fin de mes) necesita restar este adelanto
        // ya pagado el día 15, para no pagarlo dos veces.
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('planilla_liquidaciones', 'adelanto_quincena')) {
                $table->decimal('adelanto_quincena', 10, 2)->default(0)->after('adelanto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('adelanto_quincena');
        });

        Schema::dropIfExists('planilla_quincenas');
    }
};

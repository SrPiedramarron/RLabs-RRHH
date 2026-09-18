<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se descarta el enfoque "pagar en 2 partes el neto ya calculado"
        // (Opción B) — Cielo confirmó definitivamente (set. 2026) que es
        // Opción A: la quincena SÍ es un cálculo aparte a mitad de mes,
        // usando solo sueldo_base + asignación familiar (con su AFP/ONP y
        // renta 5ta proporcionales), sin comisiones/tardanzas/horas extra.
        // Ese resultado se resta después en la liquidación de fin de mes,
        // que sigue calculando TODO como siempre.
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            if (Schema::hasColumn('planilla_liquidaciones', 'quincena_1_pagada_at')) {
                $table->dropColumn(['quincena_1_pagada_at', 'quincena_2_pagada_at']);
            }

            if (! Schema::hasColumn('planilla_liquidaciones', 'adelanto_quincena')) {
                $table->decimal('adelanto_quincena', 10, 2)->default(0)->after('adelanto');
            }
        });

        if (! Schema::hasTable('planilla_quincenas')) {
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
                $table->decimal('asignacion_familiar', 10, 2)->default(0);
                $table->decimal('base_quincenal', 10, 2); // (sueldo_base + asignacion_familiar) / 2

                $table->string('sistema_pensiones');
                $table->decimal('porcentaje_pension', 6, 4)->default(0);
                $table->decimal('descuento_pension', 10, 2)->default(0);
                $table->decimal('afp_comision_flujo', 10, 2)->default(0);
                $table->decimal('afp_prima_seguro', 10, 2)->default(0);
                $table->decimal('afp_aporte_obligatorio', 10, 2)->default(0);

                $table->boolean('aplica_5ta_categoria')->default(false);
                $table->decimal('descuento_5ta_categoria', 10, 2)->default(0);

                // Lo que efectivamente se le deposita al trabajador el día 15
                // = base_quincenal - descuento_pension - descuento_5ta_categoria
                // (los 3 campos ya vienen divididos entre 2 — ver PlanillaService)
                $table->decimal('neto_pagar', 10, 2)->default(0);

                $table->unsignedBigInteger('calculado_por')->nullable();
                $table->timestamp('calculado_at')->nullable();

                $table->timestamps();

                $table->unique(['employee_id', 'periodo'], 'planilla_quincenas_unico');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('planilla_quincenas');

        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('adelanto_quincena');
            $table->timestamp('quincena_1_pagada_at')->nullable();
            $table->timestamp('quincena_2_pagada_at')->nullable();
        });
    }
};

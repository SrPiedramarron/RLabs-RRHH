<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained();
            $table->string('periodo', 7); // 'YYYY-MM' — mes en que se paga
            $table->unsignedSmallInteger('anio_ejercicio'); // año cuyas utilidades se reparten

            $table->string('nombres', 150);
            $table->string('apellidos', 150);
            $table->string('dni', 20);
            $table->string('cargo', 100)->nullable();
            $table->decimal('sueldo_base', 10, 2);

            // Insumos del reparto (todo el año fiscal)
            $table->unsignedSmallInteger('dias_trabajados_anual')->default(0);
            $table->decimal('remuneracion_anual', 10, 2)->default(0);

            // Reparto: 50% por días trabajados, 50% por remuneración (Ley),
            // sobre el pool total que ingresa RRHH desde contabilidad.
            $table->decimal('monto_por_dias', 10, 2)->default(0);
            $table->decimal('monto_por_remuneracion', 10, 2)->default(0);
            $table->decimal('monto_bruto', 10, 2)->default(0); // suma de los dos, antes del tope

            // Tope legal: 18 remuneraciones mensuales del trabajador.
            $table->decimal('tope_18_remuneraciones', 10, 2)->default(0);
            $table->boolean('tope_aplicado')->default(false);
            $table->decimal('monto_pagado', 10, 2)->default(0); // min(bruto, tope) — lo que realmente se paga

            $table->foreignId('calculado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculado_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'anio_ejercicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilidades');
    }
};

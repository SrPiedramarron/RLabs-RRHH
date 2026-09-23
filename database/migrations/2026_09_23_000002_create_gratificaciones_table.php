<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gratificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained();
            $table->string('periodo', 7); // 'YYYY-07' o 'YYYY-12' — mes de pago
            $table->enum('tipo', ['julio', 'diciembre']);
            $table->unsignedSmallInteger('anio');

            // Datos del trabajador al momento del cálculo (igual patrón que
            // planilla_liquidaciones, para que la boleta no dependa de que
            // el empleado no haya cambiado de datos después).
            $table->string('nombres', 150);
            $table->string('apellidos', 150);
            $table->string('dni', 20);
            $table->string('cargo', 100)->nullable();

            $table->decimal('sueldo_base', 10, 2);
            $table->decimal('asignacion_familiar', 10, 2)->default(0);

            // Semestre computable: de los 6 meses previos al cierre, cuántos
            // tuvo el trabajador activo (para prorratear si no completó el
            // semestre) y cuántos de esos 6 tuvieron comisiones/horas extra
            // (regla legal: solo se promedian si hubo en al menos 3 de 6).
            $table->unsignedTinyInteger('meses_computables'); // 0-6, para prorrateo
            $table->unsignedTinyInteger('meses_con_comisiones')->default(0);
            $table->decimal('promedio_comisiones', 10, 2)->default(0);
            $table->unsignedTinyInteger('meses_con_horas_extra')->default(0);
            $table->decimal('promedio_horas_extra', 10, 2)->default(0);

            $table->decimal('remuneracion_computable', 10, 2); // sueldo+asigfam+promedios
            $table->decimal('monto_gratificacion', 10, 2); // remuneracion_computable/6 * meses_computables
            $table->decimal('bonificacion_extraordinaria', 10, 2); // 9% Ley 29351 sobre monto_gratificacion
            $table->decimal('monto_total', 10, 2); // gratificacion + bonificacion

            $table->foreignId('calculado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculado_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gratificaciones');
    }
};

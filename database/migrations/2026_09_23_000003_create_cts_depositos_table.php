<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cts_depositos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained();
            $table->string('periodo', 7); // 'YYYY-05' o 'YYYY-11' — mes de depósito
            $table->enum('tipo', ['mayo', 'noviembre']);
            $table->unsignedSmallInteger('anio');

            $table->string('nombres', 150);
            $table->string('apellidos', 150);
            $table->string('dni', 20);
            $table->string('cargo', 100)->nullable();

            $table->decimal('sueldo_base', 10, 2);
            $table->decimal('asignacion_familiar', 10, 2)->default(0);

            // Semestre CTS: Nov-Abr (depósito mayo) o May-Oct (depósito noviembre).
            $table->unsignedTinyInteger('meses_computables'); // 0-6, para prorrateo
            $table->unsignedTinyInteger('meses_con_comisiones')->default(0);
            $table->decimal('promedio_comisiones', 10, 2)->default(0);
            $table->unsignedTinyInteger('meses_con_horas_extra')->default(0);
            $table->decimal('promedio_horas_extra', 10, 2)->default(0);

            // 1/6 de la gratificación percibida dentro del semestre CTS
            // (diciembre anterior para el depósito de mayo, julio de este año
            // para el de noviembre) — regla legal de remuneración computable CTS.
            $table->foreignId('gratificacion_id')->nullable()->constrained('gratificaciones')->nullOnDelete();
            $table->decimal('sexto_gratificacion', 10, 2)->default(0);

            $table->decimal('remuneracion_computable', 10, 2);
            $table->decimal('monto_cts', 10, 2); // remuneracion_computable / 12 * meses_computables

            $table->foreignId('calculado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculado_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cts_depositos');
    }
};

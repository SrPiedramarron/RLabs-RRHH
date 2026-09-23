<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidaciones_cese', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained();
            $table->date('fecha_cese');
            $table->enum('motivo_cese', [
                'renuncia', 'despido_justificado', 'despido_arbitrario', 'mutuo_disenso', 'terminacion_contrato',
            ]);

            $table->string('nombres', 150);
            $table->string('apellidos', 150);
            $table->string('dni', 20);
            $table->string('cargo', 100)->nullable();
            $table->decimal('sueldo_base', 10, 2);
            $table->decimal('asignacion_familiar', 10, 2)->default(0);

            // Vacaciones truncas
            $table->decimal('dias_vacaciones_truncas', 6, 2)->default(0);
            $table->decimal('monto_vacaciones_truncas', 10, 2)->default(0);

            // Gratificación trunca (semestre en curso al momento del cese)
            $table->decimal('meses_gratificacion_trunca', 5, 2)->default(0);
            $table->decimal('monto_gratificacion_trunca', 10, 2)->default(0);
            $table->decimal('bonificacion_extraordinaria_trunca', 10, 2)->default(0);

            // CTS trunca (semestre CTS en curso al momento del cese)
            $table->decimal('meses_cts_trunca', 5, 2)->default(0);
            $table->decimal('monto_cts_trunca', 10, 2)->default(0);

            // Indemnización por despido arbitrario — 0 si el motivo no aplica.
            $table->decimal('indemnizacion', 10, 2)->default(0);

            $table->decimal('monto_total', 10, 2);

            $table->foreignId('calculado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculado_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'fecha_cese']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones_cese');
    }
};

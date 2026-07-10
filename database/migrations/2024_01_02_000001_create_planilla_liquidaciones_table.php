<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilla_liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('periodo');          // "2026-05"
            $table->string('mes_nombre');       // "MAYO 2026"

            // ── Datos del empleado al momento del cálculo (snapshot) ──────────
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('dni');
            $table->string('cargo')->nullable();
            $table->decimal('sueldo_base', 10, 2);
            $table->string('sistema_pensiones'); // onp, afp_prima, etc.
            $table->boolean('aplica_5ta_categoria')->default(false);

            // ── Asistencia del mes ────────────────────────────────────────────
            $table->integer('dias_laborables');         // días hábiles del mes
            $table->integer('dias_trabajados');
            $table->integer('dias_falta');              // ausente no justificado
            $table->integer('dias_justificados');
            $table->integer('total_minutos_tarde');
            $table->decimal('horas_extra_diurnas', 7, 2)->default(0);    // 25%
            $table->decimal('horas_extra_nocturnas', 7, 2)->default(0);  // 35%

            // ── Ingresos ──────────────────────────────────────────────────────
            $table->decimal('sueldo_proporcional', 10, 2);  // si tuvo faltas
            $table->decimal('importe_horas_extra_diurnas', 10, 2)->default(0);
            $table->decimal('importe_horas_extra_nocturnas', 10, 2)->default(0);
            $table->decimal('comisiones', 10, 2)->default(0);
            $table->decimal('bonos_especiales', 10, 2)->default(0);      // ingreso manual

            // ── Descuentos ────────────────────────────────────────────────────
            $table->decimal('descuento_tardanzas', 10, 2)->default(0);
            $table->decimal('descuento_faltas', 10, 2)->default(0);

            // ── Totales intermedios ───────────────────────────────────────────
            $table->decimal('remuneracion_bruta', 10, 2);  // antes de ley

            // ── Descuentos de ley ─────────────────────────────────────────────
            $table->decimal('porcentaje_pension', 5, 4);   // 0.1300, 0.1023, etc.
            $table->decimal('descuento_pension', 10, 2);
            $table->decimal('descuento_5ta_categoria', 10, 2)->default(0);

            // ── Neto a pagar ──────────────────────────────────────────────────
            $table->decimal('total_descuentos', 10, 2);
            $table->decimal('neto_pagar', 10, 2);

            // ── Control ───────────────────────────────────────────────────────
            $table->foreignId('calculado_por')->nullable()->constrained('users');
            $table->timestamp('calculado_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'periodo']);
            $table->index(['company_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilla_liquidaciones');
    }
};

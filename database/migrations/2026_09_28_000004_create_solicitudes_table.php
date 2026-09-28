<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solicitudes')) {
            return;
        }

        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained();

            // 'vacaciones' hoy; 'permiso' y 'correccion_horas' en siguientes
            // pasadas — misma tabla, mismo flujo de aprobación.
            $table->enum('tipo', ['vacaciones', 'permiso', 'correccion_horas']);
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');

            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();

            // Para 'correccion_horas': el día puntual y las horas propuestas.
            $table->date('fecha_registro')->nullable();
            $table->time('hora_entrada_solicitada')->nullable();
            $table->time('hora_salida_solicitada')->nullable();

            // Para 'permiso': código Tabla 21 SUNAT sugerido por el trabajador
            // (RRHH lo puede corregir al aprobar).
            $table->string('motivo_suspension_plame', 10)->nullable();

            $table->text('motivo')->nullable();
            $table->string('adjunto_path')->nullable();

            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->text('comentario_revision')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};

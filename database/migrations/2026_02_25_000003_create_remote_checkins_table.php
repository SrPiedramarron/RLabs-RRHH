<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registros de marcado remoto (entrada/salida desde la PWA móvil).
     * Se procesan para generar/actualizar el attendance_record correspondiente.
     */
    public function up(): void
    {
        Schema::create('remote_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Tipo de marcación
            $table->enum('tipo', ['entrada', 'salida']);
            $table->dateTime('fecha_hora');

            // Geolocalización
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('precision_metros', 8, 2)->nullable()
                ->comment('Precisión del GPS en metros reportada por el dispositivo');

            // Foto / validación facial
            $table->string('foto_path')->nullable()
                ->comment('Selfie tomada en el momento del marcado');
            $table->enum('estado_facial', [
                'pendiente',    // aún no procesado
                'aprobado',     // rostro coincide con foto de perfil
                'rechazado',    // rostro no coincide
                'sin_perfil',   // el empleado no tiene foto de perfil aún
                'error',        // fallo técnico en la validación
            ])->default('pendiente');
            $table->decimal('confianza_facial', 5, 4)->nullable()
                ->comment('Score 0.0000–1.0000 devuelto por face_recognition');

            // Metadatos del dispositivo
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Vínculo con el registro de asistencia procesado
            $table->foreignId('attendance_record_id')->nullable()
                ->constrained()->nullOnDelete()
                ->comment('attendance_record que se creó/actualizó con este checkin');
            $table->enum('estado_procesado', [
                'pendiente',    // esperando ser procesado
                'procesado',    // ya se reflejó en attendance_records
                'rechazado',    // no se procesó por fallo facial u otro motivo
                'manual',       // aprobado manualmente por el supervisor
            ])->default('pendiente');
            $table->text('notas_supervisor')->nullable();

            $table->timestamps();

            // Índices para consultas frecuentes
            $table->index(['employee_id', 'fecha_hora']);
            $table->index(['company_id', 'fecha_hora']);
            $table->index('estado_procesado');
            $table->index('estado_facial');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_checkins');
    }
};

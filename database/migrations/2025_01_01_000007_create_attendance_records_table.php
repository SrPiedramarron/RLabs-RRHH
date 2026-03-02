<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            // Marcaciones (exigidas por DS 004-2006-TR Art.2)
            $table->dateTime('hora_entrada')->nullable();
            $table->dateTime('hora_salida')->nullable();
            // Cálculos automáticos
            $table->integer('minutos_tarde')->default(0);
            $table->integer('minutos_trabajados')->default(0);
            $table->decimal('horas_ordinarias', 5, 2)->default(0);
            $table->decimal('horas_extra_diurnas', 5, 2)->default(0);    // recargo 25%
            $table->decimal('horas_extra_nocturnas', 5, 2)->default(0);  // recargo 35%
            // Estado del día
            $table->enum('estado', [
                'presente',
                'tarde',
                'ausente',
                'feriado',
                'descanso',
                'permiso',
                'vacaciones',
            ])->default('ausente');
            // Justificaciones / correcciones
            $table->boolean('justificado')->default(false);
            $table->text('observacion')->nullable();
            $table->boolean('corregido_manualmente')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};

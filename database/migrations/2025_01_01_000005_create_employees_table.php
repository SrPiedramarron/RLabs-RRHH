<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            // Datos personales (exigidos por SUNAFIL DS 004-2006-TR Art.2)
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('dni', 8);
            // Datos laborales
            $table->string('codigo_empleado', 20)->nullable();
            $table->string('cargo', 100)->nullable();
            $table->date('fecha_ingreso');
            $table->date('fecha_cese')->nullable();
            // Configuración SUNAFIL
            $table->boolean('exonerado_registro')->default(false);
            $table->string('motivo_exoneracion', 200)->nullable();
            // Relación con reloj biométrico
            $table->integer('reloj_uid')->nullable();
            $table->integer('reloj_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['dni', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};

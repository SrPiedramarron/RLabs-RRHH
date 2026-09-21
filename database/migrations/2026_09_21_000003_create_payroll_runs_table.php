<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('periodo_tipo', ['quincenal', 'mensual']);
            $table->unsignedTinyInteger('quincena')->nullable(); // 1 o 2, solo si periodo_tipo = quincenal
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->date('fecha_pago');
            $table->string('moneda', 3)->default('PEN');
            // Cuenta de cargo de la empresa para el archivo bancario (20 chars, ver BbvaHaberesExporter)
            $table->string('cuenta_cargo_pago', 22)->nullable();
            $table->string('referencia', 25)->nullable(); // ej. "1ERA QUINCENA SETIEMBRE"
            $table->enum('estado', ['borrador', 'aprobada', 'pagada'])->default('borrador');
            $table->timestamp('aprobada_en')->nullable();
            $table->foreignId('aprobada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'periodo_tipo', 'quincena', 'mes', 'anio'], 'payroll_runs_unique_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};

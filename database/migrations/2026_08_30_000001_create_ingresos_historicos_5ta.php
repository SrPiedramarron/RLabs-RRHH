<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingresos_historicos_5ta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('periodo'); // "2026-01", "2026-02"...
            $table->string('concepto'); // "SUELDO + ASIG FAM", "COMISIONES", "BONO", etc. (texto libre del Excel)
            $table->decimal('monto', 10, 2);
            $table->string('fuente')->default('import_excel'); // de dónde vino el dato
            $table->timestamps();

            $table->index(['employee_id', 'periodo']);
            $table->unique(['employee_id', 'periodo', 'concepto']); // evita duplicar si se reimporta
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos_historicos_5ta');
    }
};

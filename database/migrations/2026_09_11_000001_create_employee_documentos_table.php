<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('tipo');              // libre por ahora: "Contrato", "DNI", "CV", etc. Cuando RRHH defina la lista fija, se puede convertir a un Select con opciones.
            $table->string('nombre')->nullable(); // descripción/etiqueta opcional, ej: "Contrato renovado 2026"
            $table->string('archivo');            // path en storage
            $table->date('fecha_documento')->nullable(); // ej: fecha de firma del contrato
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documentos');
    }
};

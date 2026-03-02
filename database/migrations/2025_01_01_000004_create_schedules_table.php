<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->time('hora_entrada');
            $table->time('hora_salida');
            $table->integer('tolerancia_minutos')->default(5);
            $table->time('refrigerio_inicio')->nullable();
            $table->time('refrigerio_fin')->nullable();
            // Días laborables: [1,2,3,4,5] = Lunes a Viernes
            $table->json('dias_laborables');
            $table->boolean('es_nocturno')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};

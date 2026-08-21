<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afp_tasas', function (Blueprint $table) {
            $table->id();
            $table->string('afp'); // afp_prima, afp_integra, afp_habitat, afp_profuturo
            $table->decimal('comision_flujo', 6, 4);       // % sobre remuneración (ej. 0.0147)
            $table->decimal('prima_seguro', 6, 4);         // % fijo, igual para las 4 AFP (ej. 0.0137)
            $table->decimal('aporte_obligatorio', 6, 4)->default(0.1000); // 10% fijo por ley
            $table->decimal('tope_remuneracion_asegurable', 10, 2); // ej. 12209.11
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable(); // null = vigente actualmente
            $table->timestamps();

            $table->index(['afp', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afp_tasas');
    }
};

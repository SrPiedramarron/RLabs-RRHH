<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros_legales', function (Blueprint $table) {
            $table->id();
            $table->string('clave'); // rmv, uit, tasa_onp
            $table->string('descripcion')->nullable();
            $table->decimal('valor', 12, 4);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable(); // null = vigente actualmente
            $table->timestamps();

            $table->index(['clave', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_legales');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_concepts', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();       // ej. 'SUELDO_BASICO', 'ONP', 'DESC_TARDANZA'
            $table->string('nombre', 100);
            $table->enum('tipo', ['ingreso', 'descuento']);
            $table->boolean('afecto_onp_afp')->default(false);
            $table->boolean('afecto_renta_5ta')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_concepts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comision_escalas')) {
            return;
        }

        Schema::create('comision_escalas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('monto_desde', 14, 2);
            $table->decimal('porcentaje', 6, 4); // fracción: 0.007 = 0.7%
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_escalas');
    }
};

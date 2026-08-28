<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            // Descuentos manuales (préstamos, adelantos, quincenas) que se
            // ingresan al calcular la planilla, igual que bonos_especiales
            // pero restando. NO afectan EsSalud, AFP/ONP, 5ta categoría —
            // solo se restan directo del neto a pagar.
            $table->decimal('otros_descuentos', 10, 2)->default(0)->after('bonos_especiales');
        });
    }

    public function down(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('otros_descuentos');
        });
    }
};

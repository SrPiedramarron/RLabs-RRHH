<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Monto fijo mensual del seguro vida ley (prima anual que paga
            // la empresa ÷ 12). NO es un porcentaje calculado — es lo que
            // realmente factura la aseguradora, prorrateado. Cambia solo
            // cuando se renueva la póliza (confirmado con RRHH, ago 2026).
            $table->decimal('seguro_vida_mensual', 8, 2)->default(0)->after('bono_encargatura');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('seguro_vida_mensual');
        });
    }
};

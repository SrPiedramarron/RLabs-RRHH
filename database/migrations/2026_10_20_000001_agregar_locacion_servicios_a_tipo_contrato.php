<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employees MODIFY tipo_contrato ENUM('indeterminado','inicio_incremento_actividad','necesidad_mercado','obra_servicio_especifico','locacion_servicios') NULL");
    }

    public function down(): void
    {
        DB::table('employees')->where('tipo_contrato', 'locacion_servicios')->update(['tipo_contrato' => null]);
        DB::statement("ALTER TABLE employees MODIFY tipo_contrato ENUM('indeterminado','inicio_incremento_actividad','necesidad_mercado','obra_servicio_especifico') NULL");
    }
};

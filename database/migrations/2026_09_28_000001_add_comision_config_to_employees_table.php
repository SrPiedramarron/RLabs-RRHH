<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'tipo_base_comision')) {
                $table->enum('tipo_base_comision', ['cartera_propia', 'total_empresa'])
                    ->nullable()
                    ->default('cartera_propia')
                    ->after('aplica_comision');
            }

            if (! Schema::hasColumn('employees', 'porcentaje_comision')) {
                // Fracción (0.015 = 1.5%), no porcentaje entero.
                $table->decimal('porcentaje_comision', 6, 4)->nullable()->after('tipo_base_comision');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['tipo_base_comision', 'porcentaje_comision']);
        });
    }
};

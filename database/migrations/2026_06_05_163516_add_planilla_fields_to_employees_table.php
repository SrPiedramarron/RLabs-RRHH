<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Remuneración
            $table->decimal('sueldo_base', 10, 2)->default(0)->after('cargo');
            
            // Sistema de pensiones
            $table->enum('sistema_pensiones', [
                'onp',
                'afp_prima',
                'afp_integra', 
                'afp_habitat',
                'afp_profuturo'
            ])->default('onp')->after('sueldo_base');
            
            // 5ta categoría
            $table->boolean('aplica_5ta_categoria')->default(false)->after('sistema_pensiones');
            
            // Comisiones
            $table->boolean('aplica_comision')->default(false)->after('aplica_5ta_categoria');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'sueldo_base',
                'sistema_pensiones', 
                'aplica_5ta_categoria',
                'aplica_comision',
            ]);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'fecha_fin_contrato')) {
                // Fecha de fin del contrato VIGENTE. Al crear al trabajador,
                // normalmente es igual a fecha_cese (contrato a plazo fijo).
                // Cuando se renueva, ambas se actualizan juntas — ver acción
                // "Renovar contrato" en EmployeeResource y ContractRenewal.
                $table->date('fecha_fin_contrato')->nullable()->after('fecha_ingreso');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('fecha_fin_contrato');
        });
    }
};

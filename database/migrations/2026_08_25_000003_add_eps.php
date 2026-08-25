<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Costo mensual del plan EPS del trabajador, CON IGV, tal como
            // factura el proveedor (hoy solo Sanitas Perú, RUC 2052347076).
            // 0 = el trabajador no tiene EPS, va 100% por EsSalud.
            $table->decimal('monto_eps_mensual_con_igv', 8, 2)->default(0)->after('movilidad_diaria');
        });

        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->decimal('eps_credito', 10, 2)->default(0)->after('essalud_empleador');
            $table->decimal('eps_aporte_empresa', 10, 2)->default(0)->after('eps_credito');
            $table->decimal('eps_descuento_trabajador', 10, 2)->default(0)->after('eps_aporte_empresa');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('monto_eps_mensual_con_igv');
        });

        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn(['eps_credito', 'eps_aporte_empresa', 'eps_descuento_trabajador']);
        });
    }
};

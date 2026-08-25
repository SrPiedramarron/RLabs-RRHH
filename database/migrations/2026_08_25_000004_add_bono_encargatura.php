<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Bono fijo mensual por encargatura de un cargo/función adicional.
            // A diferencia de movilidad, ESTE SÍ afecta EsSalud y AFP/ONP
            // (confirmado con RRHH, código PLAME 1007, catálogo registrado
            // InProcess: afectaciones SI a EsSalud/SNP/SPP).
            $table->decimal('bono_encargatura', 8, 2)->default(0)->after('monto_eps_mensual_con_igv');
        });

        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->decimal('bono_encargatura', 10, 2)->default(0)->after('bono_movilidad');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('bono_encargatura');
        });

        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('bono_encargatura');
        });
    }
};

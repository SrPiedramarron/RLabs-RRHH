<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Monto de movilidad por día efectivamente trabajado. 0 = no aplica.
            // No afecta EsSalud ni AFP/ONP — solo entra a la base de 5ta categoría
            // y se paga aparte en el neto (confirmado con RRHH, ago 2026).
            $table->decimal('movilidad_diaria', 8, 2)->default(0)->after('aplica_asignacion_familiar');
        });

        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->decimal('bono_movilidad', 10, 2)->default(0)->after('asignacion_familiar');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('movilidad_diaria');
        });

        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('bono_movilidad');
        });
    }
};

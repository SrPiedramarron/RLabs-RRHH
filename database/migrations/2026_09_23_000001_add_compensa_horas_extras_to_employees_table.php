<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'compensa_horas_extras')) {
                // Si está activo, las horas extra del trabajador se compensan con
                // tiempo libre otro día (no se le paga en planilla). Las horas
                // siguen quedando registradas en AttendanceRecord para referencia,
                // solo se deja de convertirlas a un importe pagable.
                $table->boolean('compensa_horas_extras')->default(false)->after('aplica_comision');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('compensa_horas_extras');
        });
    }
};

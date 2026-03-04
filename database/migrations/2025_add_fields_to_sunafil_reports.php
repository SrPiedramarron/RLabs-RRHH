<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si tienes una tabla sunafil_reports o similar, agrega aquí los campos.
        // Si el reporte es on-the-fly (sin tabla), esta migración no es necesaria
        // y los filtros van directo en el formulario del Resource.

        // Ejemplo si guardas configuración de reportes:
        if (Schema::hasTable('report_configs')) {
            Schema::table('report_configs', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('incluye_refrigerio')->default(false);
            });
        }

        // attendance_records: asegurarse de tener los campos de refrigerio
        if (Schema::hasTable('attendance_records')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                if (!Schema::hasColumn('attendance_records', 'inicio_refrigerio')) {
                    $table->time('inicio_refrigerio')->nullable()->after('hora_salida');
                }
                if (!Schema::hasColumn('attendance_records', 'fin_refrigerio')) {
                    $table->time('fin_refrigerio')->nullable()->after('inicio_refrigerio');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_records')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dropColumnIfExists('inicio_refrigerio');
                $table->dropColumnIfExists('fin_refrigerio');
            });
        }
    }
};

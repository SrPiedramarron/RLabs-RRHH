<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla_quincenas', function (Blueprint $table) {
            if (! Schema::hasColumn('planilla_quincenas', 'asignacion_familiar')) {
                $table->decimal('asignacion_familiar', 10, 2)->default(0)->after('sueldo_base');
            }
        });

        // La versión vieja de esta tabla (14 set.) tenía la columna
        // 'base_quincena' en vez de 'base_quincenal'. Si existe esa
        // columna vieja, la renombramos con SQL directo (evita depender
        // de doctrine/dbal, que renameColumn() de Laravel necesita).
        if (Schema::hasColumn('planilla_quincenas', 'base_quincena') && ! Schema::hasColumn('planilla_quincenas', 'base_quincenal')) {
            DB::statement('ALTER TABLE planilla_quincenas CHANGE base_quincena base_quincenal DECIMAL(10,2) NOT NULL DEFAULT 0');
        } elseif (! Schema::hasColumn('planilla_quincenas', 'base_quincenal')) {
            Schema::table('planilla_quincenas', function (Blueprint $table) {
                $table->decimal('base_quincenal', 10, 2)->default(0)->after('asignacion_familiar');
            });
        }
    }

    public function down(): void
    {
        Schema::table('planilla_quincenas', function (Blueprint $table) {
            if (Schema::hasColumn('planilla_quincenas', 'asignacion_familiar')) {
                $table->dropColumn('asignacion_familiar');
            }
        });
    }
};

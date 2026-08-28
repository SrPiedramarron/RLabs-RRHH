<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('aplica_asignacion_familiar')->default(false)->after('aplica_comision');
        });

        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->decimal('asignacion_familiar', 10, 2)->default(0)->after('comisiones');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('aplica_asignacion_familiar');
        });

        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('asignacion_familiar');
        });
    }
};

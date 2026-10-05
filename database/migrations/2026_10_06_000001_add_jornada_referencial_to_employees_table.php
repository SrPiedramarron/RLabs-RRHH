<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Jornada ordinaria referencial para trabajadores exonerados de
            // registro (no fiscalizados): no marcan, así que sus horas se
            // calculan solas por los días del periodo de corte.
            $table->decimal('jornada_ref_horas_dia', 4, 2)->default(8.50)->after('exonerado_registro');
            $table->decimal('jornada_ref_horas_sabado', 4, 2)->default(4.00)->after('jornada_ref_horas_dia');
            $table->unsignedTinyInteger('jornada_ref_sabados_mes')->default(2)->after('jornada_ref_horas_sabado');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['jornada_ref_horas_dia', 'jornada_ref_horas_sabado', 'jornada_ref_sabados_mes']);
        });
    }
};

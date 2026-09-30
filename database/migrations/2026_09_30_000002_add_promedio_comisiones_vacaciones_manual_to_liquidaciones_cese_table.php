<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidaciones_cese', function (Blueprint $table) {
            if (! Schema::hasColumn('liquidaciones_cese', 'promedio_comisiones_vacaciones_manual')) {
                $table->decimal('promedio_comisiones_vacaciones_manual', 10, 2)->nullable()->after('dias_vacaciones_truncas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones_cese', function (Blueprint $table) {
            $table->dropColumn('promedio_comisiones_vacaciones_manual');
        });
    }
};

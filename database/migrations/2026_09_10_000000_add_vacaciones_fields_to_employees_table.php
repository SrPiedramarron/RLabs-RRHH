<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'fecha_ultima_vacacion')) {
                $table->date('fecha_ultima_vacacion')->nullable()->after('fecha_ingreso');
            }

            if (! Schema::hasColumn('employees', 'dias_tomados')) {
                $table->unsignedInteger('dias_tomados')->default(0)->after('fecha_ultima_vacacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'dias_tomados')) {
                $table->dropColumn('dias_tomados');
            }

            if (Schema::hasColumn('employees', 'fecha_ultima_vacacion')) {
                $table->dropColumn('fecha_ultima_vacacion');
            }
        });
    }
};

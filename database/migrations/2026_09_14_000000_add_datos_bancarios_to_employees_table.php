<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'banco')) {
                $table->string('banco')->nullable()->after('seguro_vida_mensual');
            }

            if (! Schema::hasColumn('employees', 'numero_cuenta')) {
                $table->string('numero_cuenta')->nullable()->after('banco');
            }

            if (! Schema::hasColumn('employees', 'cci')) {
                $table->string('cci', 20)->nullable()->after('numero_cuenta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['banco', 'numero_cuenta', 'cci']);
        });
    }
};

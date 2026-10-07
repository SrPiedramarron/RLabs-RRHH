<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cts_depositos', function (Blueprint $table) {
            // Días del mes incompleto (ingreso a mitad de semestre): la CTS se
            // computa por meses completos + días trabajados.
            $table->unsignedTinyInteger('dias_computables')->default(0)->after('meses_computables');
        });
    }

    public function down(): void
    {
        Schema::table('cts_depositos', function (Blueprint $table) {
            $table->dropColumn('dias_computables');
        });
    }
};

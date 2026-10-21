<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('planilla_quincenas', 'descuento_tardanzas')) {
            Schema::table('planilla_quincenas', function (Blueprint $table) {
                $table->unsignedSmallInteger('dias_falta')->default(0);
                $table->unsignedInteger('minutos_tarde')->default(0);
                $table->decimal('descuento_faltas', 10, 2)->default(0);
                $table->decimal('descuento_tardanzas', 10, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::table('planilla_quincenas', fn (Blueprint $t) => $t->dropColumn(['dias_falta', 'minutos_tarde', 'descuento_faltas', 'descuento_tardanzas']));
    }
};

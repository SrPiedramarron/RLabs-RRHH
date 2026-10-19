<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'frecuencia_pago')) {
            Schema::table('employees', function (Blueprint $table) {
                // null = sigue la configuración de la empresa (planilla) o quincenal (locadores).
                $table->string('frecuencia_pago', 10)->nullable()->after('tipo_vinculo');
            });
        }
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('frecuencia_pago'));
    }
};

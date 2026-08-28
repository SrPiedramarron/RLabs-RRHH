<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            // 'otros_descuentos' ya existía (ayer) y quedaba mapeado a 0701.
            // Ahora se reparte en dos conceptos PLAME distintos:
            $table->decimal('adelanto', 10, 2)->default(0)->after('otros_descuentos'); // código 0701
            // 'otros_descuentos' pasa a significar código 0706 exclusivamente
            // (préstamos y otros) — YA NO se usa para EPS.
        });
    }

    public function down(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('adelanto');
        });
    }
};

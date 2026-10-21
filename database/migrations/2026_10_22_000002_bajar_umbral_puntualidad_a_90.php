<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// RRHH (oct. 2026): el 96 % era muy estricto; el umbral del beneficio pasa a 90 %.
// Solo se cambia si sigue en el valor inicial (96): si RRHH ya lo ajustó, se respeta.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('parametros_legales')
            ->where('clave', 'umbral_puntualidad_beneficio')
            ->where('valor', 96)
            ->update(['valor' => 90, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('parametros_legales')
            ->where('clave', 'umbral_puntualidad_beneficio')
            ->where('valor', 90)
            ->update(['valor' => 96, 'updated_at' => now()]);
    }
};

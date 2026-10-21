<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('parametros_legales')->where('clave', 'umbral_puntualidad_beneficio')->exists()) {
            DB::table('parametros_legales')->insert([
                'clave'         => 'umbral_puntualidad_beneficio',
                'descripcion'   => '% mínimo de puntualidad para el beneficio de sábados libres',
                'valor'         => 90,
                'vigente_desde' => '2026-01-01',
                'vigente_hasta' => null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('parametros_legales')->where('clave', 'umbral_puntualidad_beneficio')->delete();
    }
};

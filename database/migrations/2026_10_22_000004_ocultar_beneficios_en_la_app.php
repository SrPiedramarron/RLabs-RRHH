<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// RRHH (oct. 2026): la sección Beneficios se publicó sin aprobación; queda oculta hasta que se apruebe.
// Se vuelve a mostrar poniendo 1 en Administración → Parámetros Legales ("Mostrar beneficios en la app").
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('parametros_legales')->where('clave', 'beneficios_visibles')->exists()) {
            DB::table('parametros_legales')->insert([
                'clave'         => 'beneficios_visibles',
                'descripcion'   => 'Mostrar la sección Beneficios en la app (1 = sí, 0 = no)',
                'valor'         => 0,
                'vigente_desde' => '2026-01-01',
                'vigente_hasta' => null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('parametros_legales')->where('clave', 'beneficios_visibles')->delete();
    }
};

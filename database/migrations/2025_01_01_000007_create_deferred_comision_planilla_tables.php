<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Las migraciones 2024_01_* (comisiones y planilla) referencian employees/companies, que se crean
 * en 2025_01_01_*. En una base nueva se saltan y aquí se crean, ya con las tablas padre listas.
 * En bases existentes (producción) las tablas ya están y esta migración no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'comision_uploads' => '2024_01_01_000001_create_comision_uploads_table.php',
            'comision_detalles' => '2024_01_01_000002_create_comision_detalles_table.php',
            'planilla_liquidaciones' => '2024_01_02_000001_create_planilla_liquidaciones_table.php',
        ] as $table => $file) {
            if (! Schema::hasTable($table)) {
                (require __DIR__.'/'.$file)->up();
            }
        }
    }

    public function down(): void
    {
        // Las tablas las elimina el down() de las migraciones originales.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $columnas = [
                'fecha_nacimiento'    => fn ($t) => $t->date('fecha_nacimiento')->nullable()->after('fecha_ingreso'),
                'celular'             => fn ($t) => $t->string('celular', 20)->nullable()->after('email'),
                'correo_corporativo'  => fn ($t) => $t->string('correo_corporativo', 150)->nullable()->after('celular'),
                'correo_personal'     => fn ($t) => $t->string('correo_personal', 150)->nullable()->after('correo_corporativo'),
                'centro_costos'       => fn ($t) => $t->string('centro_costos', 100)->nullable()->after('department_id'),
                'banco_cts'           => fn ($t) => $t->string('banco_cts', 100)->nullable()->after('cci'),
                'numero_cuenta_cts'   => fn ($t) => $t->string('numero_cuenta_cts', 30)->nullable()->after('banco_cts'),
                'cci_cts'             => fn ($t) => $t->string('cci_cts', 20)->nullable()->after('numero_cuenta_cts'),
            ];

            foreach ($columnas as $nombre => $definir) {
                if (! Schema::hasColumn('employees', $nombre)) {
                    $definir($table);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_nacimiento', 'celular', 'correo_corporativo', 'correo_personal',
                'centro_costos', 'banco_cts', 'numero_cuenta_cts', 'cci_cts',
            ]);
        });
    }
};

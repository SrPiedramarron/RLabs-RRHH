<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 'L' = DNI, 'C' = Carné Extranjería, 'P' = Pasaporte (mismo código que exige el banco)
            if (! Schema::hasColumn('employees', 'doi_tipo_bancario')) {
                $table->string('doi_tipo_bancario', 1)->default('L')->after('dni');
            }

            // La columna 'banco' ya existía (varchar 255, nullable) desde antes de este módulo
            // de planillas — se reutiliza en vez de crear una segunda columna con el mismo nombre.

            // Cuenta propia del banco (20 chars) o CCI interbancario (20 chars) — mismo campo,
            // 'tipo_cuenta_pago' indica cuál es.
            if (! Schema::hasColumn('employees', 'cuenta_pago')) {
                $table->string('cuenta_pago', 20)->nullable()->after('banco');
            }

            if (! Schema::hasColumn('employees', 'tipo_cuenta_pago')) {
                $table->enum('tipo_cuenta_pago', ['propia', 'cci'])->nullable()->after('cuenta_pago');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['doi_tipo_bancario', 'cuenta_pago', 'tipo_cuenta_pago']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Códigos INEI del domicilio: departamento (2), provincia (4), distrito (6).
            $table->string('ubigeo_departamento', 2)->nullable()->after('direccion');
            $table->string('ubigeo_provincia', 4)->nullable()->after('ubigeo_departamento');
            $table->string('ubigeo_distrito', 6)->nullable()->after('ubigeo_provincia');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['ubigeo_departamento', 'ubigeo_provincia', 'ubigeo_distrito']);
        });
    }
};

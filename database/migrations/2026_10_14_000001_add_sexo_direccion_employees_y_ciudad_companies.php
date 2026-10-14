<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('sexo', 1)->nullable()->after('fecha_nacimiento'); // M / F
            $table->string('direccion', 255)->nullable()->after('sexo');
        });

        Schema::table('companies', function (Blueprint $table) {
            // Ciudad con la que se fechan los certificados y constancias.
            $table->string('ciudad', 60)->nullable()->after('direccion');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['sexo', 'direccion']);
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('ciudad');
        });
    }
};

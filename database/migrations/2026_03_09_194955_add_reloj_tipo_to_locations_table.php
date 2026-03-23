<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('reloj_tipo')->default('zkbio')->after('reloj_modelo');
        });

        // Actualizar la sede existente
        DB::table('locations')->where('id', 1)->update(['reloj_tipo' => 'zkbio']);
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('reloj_tipo');
        });
    }
};

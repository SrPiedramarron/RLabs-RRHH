<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            // Ruta del PDF ya firmado con DNIe por el gerente general.
            // NULL = todavía no se ha subido la versión firmada.
            $table->string('boleta_firmada_path')->nullable()->after('calculado_at');
            $table->timestamp('boleta_firmada_at')->nullable()->after('boleta_firmada_path');
            $table->foreignId('boleta_firmada_por')->nullable()->after('boleta_firmada_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('planilla_liquidaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boleta_firmada_por');
            $table->dropColumn(['boleta_firmada_path', 'boleta_firmada_at']);
        });
    }
};

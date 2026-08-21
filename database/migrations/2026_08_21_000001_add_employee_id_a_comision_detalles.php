<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comision_detalles', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('vendedor')
                ->constrained('employees')->nullOnDelete();
        });

        Schema::table('comision_uploads', function (Blueprint $table) {
            // Lista de nombres de 'vendedor' que no matchearon ningún empleado,
            // para poder avisar en la notificación de Filament al terminar el upload.
            $table->json('vendedores_sin_match')->nullable()->after('error_mensaje');
        });
    }

    public function down(): void
    {
        Schema::table('comision_detalles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });

        Schema::table('comision_uploads', function (Blueprint $table) {
            $table->dropColumn('vendedores_sin_match');
        });
    }
};

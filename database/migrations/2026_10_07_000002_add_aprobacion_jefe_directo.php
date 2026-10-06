<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Jefe directo: aprueba primero las vacaciones y permisos de su equipo.
            $table->foreignId('jefe_directo_id')->nullable()->after('cargo')
                ->constrained('employees')->nullOnDelete();
        });

        Schema::table('solicitudes', function (Blueprint $table) {
            $table->foreignId('jefe_id')->nullable()->after('employee_id')
                ->constrained('employees')->nullOnDelete();
            $table->timestamp('jefe_resuelto_at')->nullable();
            $table->string('comentario_jefe', 500)->nullable();
            $table->timestamp('escalada_at')->nullable(); // pasó a RRHH por falta de respuesta
        });

        DB::statement("ALTER TABLE solicitudes MODIFY estado ENUM('pendiente_jefe','pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente'");

        // Horas que se espera la respuesta del jefe antes de pasar a RRHH.
        DB::table('parametros_legales')->insert([
            'clave'         => 'horas_espera_jefe',
            'descripcion'   => 'Horas de espera de la respuesta del jefe directo antes de pasar la solicitud a RRHH',
            'valor'         => 6,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('parametros_legales')->where('clave', 'horas_espera_jefe')->delete();
        DB::table('solicitudes')->where('estado', 'pendiente_jefe')->update(['estado' => 'pendiente']);
        DB::statement("ALTER TABLE solicitudes MODIFY estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente'");

        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jefe_id');
            $table->dropColumn(['jefe_resuelto_at', 'comentario_jefe', 'escalada_at']);
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jefe_directo_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Revisión semanal de cruces entre empresas como un recordatorio más (Administración → Recordatorios):
// los correos, el día y la hora se cambian desde la pantalla. No pertenece a una empresa (company_id nulo).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE recordatorios MODIFY company_id BIGINT UNSIGNED NULL');

        if (! Schema::hasColumn('recordatorios', 'dia_semana')) {
            Schema::table('recordatorios', function (Blueprint $table) {
                $table->unsignedTinyInteger('dia_semana')->nullable()->after('dia_mes'); // 1 = lunes … 7 = domingo
            });
        }

        if (! DB::table('recordatorios')->where('tipo', 'cruces_empresas')->exists()) {
            // Destinatarios iniciales: los super administradores; RRHH los cambia desde la pantalla.
            $correos = DB::table('users as u')
                ->join('model_has_roles as m', function ($j) {
                    $j->on('m.model_id', '=', 'u.id')->where('m.model_type', 'App\Models\User');
                })
                ->join('roles as r', 'r.id', '=', 'm.role_id')
                ->where('r.name', 'super_admin')->whereNotNull('u.email')->pluck('u.email')->unique()->implode('; ');

            $ahora = now();
            DB::table('recordatorios')->insert([
                'company_id' => null, 'tipo' => 'cruces_empresas', 'activo' => true,
                'destinatarios' => $correos,
                'modo' => 'dia_semana', 'dia_semana' => 1, 'hora' => '07:00',
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('recordatorios')->where('tipo', 'cruces_empresas')->delete();
    }
};

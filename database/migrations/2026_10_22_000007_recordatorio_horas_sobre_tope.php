<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Revisión semanal de horas ordinarias sobre el tope del horario como un recordatorio más (Administración → Recordatorios).
// Los correos, el día y la hora se cambian desde esa pantalla; los días que revisa y la tolerancia, en Parámetros del sistema.
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['revision_horas_dias_atras', 'Días hacia atrás que revisa el aviso semanal de horas ordinarias sobre el tope', 7],
            ['revision_horas_tolerancia', 'Tolerancia en horas sobre el tope del horario antes de avisar', 0.02],
        ] as [$clave, $descripcion, $valor]) {
            if (! DB::table('parametros_legales')->where('clave', $clave)->exists()) {
                DB::table('parametros_legales')->insert([
                    'clave' => $clave, 'descripcion' => $descripcion, 'valor' => $valor,
                    'vigente_desde' => '2026-01-01', 'vigente_hasta' => null, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        if (! DB::table('recordatorios')->where('tipo', 'horas_sobre_tope')->exists()) {
            $destino = DB::table('recordatorios')->where('tipo', 'cruces_empresas')->value('destinatarios');
            DB::table('recordatorios')->insert([
                'company_id' => null, 'tipo' => 'horas_sobre_tope', 'activo' => true,
                'destinatarios' => $destino ?? '',
                'modo' => 'dia_semana', 'dia_semana' => 1, 'hora' => '07:15',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('recordatorios')->where('tipo', 'horas_sobre_tope')->delete();
        DB::table('parametros_legales')->whereIn('clave', ['revision_horas_dias_atras', 'revision_horas_tolerancia'])->delete();
    }
};

<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Horarios con el mismo nombre dentro de una misma empresa. Si son idénticos en entrada, salida, tolerancia,
 * refrigerio, días y turno, se unifican en el más antiguo (los trabajadores pasan a él y el repetido se borra).
 * Si difieren en algo, solo se informa. Simula por defecto.
 */
class UnificarHorariosRepetidos extends Command
{
    protected $signature = 'rrhh:unificar-horarios-repetidos {--aplicar : Guardar (sin esto solo simula)}';
    protected $description = 'Unifica los horarios repetidos (mismo nombre y mismos datos) dentro de una empresa';

    private function firma(Schedule $s): string
    {
        $dias = is_array($s->dias_laborables) ? $s->dias_laborables : json_decode((string) $s->dias_laborables, true);
        sort($dias);

        return implode('|', [
            substr((string) $s->hora_entrada, 0, 5), substr((string) $s->hora_salida, 0, 5), (int) $s->tolerancia_minutos,
            substr((string) $s->refrigerio_inicio, 0, 5), substr((string) $s->refrigerio_fin, 0, 5),
            implode(',', $dias ?? []), (int) $s->es_nocturno,
        ]);
    }

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $this->info($aplicar ? '== APLICANDO ==' : '== SIMULACIÓN (agrega --aplicar para guardar) ==');
        $filas = [];

        DB::beginTransaction();

        $grupos = Schedule::withoutGlobalScopes()->orderBy('id')->get()
            ->groupBy(fn ($s) => $s->company_id . '|' . Str::upper(Str::ascii(trim($s->nombre))))
            ->filter(fn ($g) => $g->count() > 1);

        foreach ($grupos as $grupo) {
            $principal = $grupo->first();
            foreach ($grupo->slice(1) as $dup) {
                $usan = Employee::withoutGlobalScopes()->where('schedule_id', $dup->id)->count()
                    + DB::table('employee_schedules')->where('schedule_id', $dup->id)->count();

                if ($this->firma($principal) !== $this->firma($dup)) {
                    $filas[] = [$dup->nombre, $dup->company_id, "#{$principal->id} y #{$dup->id}", $usan, 'DIFIEREN en horas/días: se deja para revisar a mano'];
                    continue;
                }

                Employee::withoutGlobalScopes()->where('schedule_id', $dup->id)->update(['schedule_id' => $principal->id]);

                // Horarios adicionales: pasar al principal evitando duplicar el par (trabajador, horario)
                foreach (DB::table('employee_schedules')->where('schedule_id', $dup->id)->get() as $p) {
                    $ya = DB::table('employee_schedules')->where('employee_id', $p->employee_id)->where('schedule_id', $principal->id)->exists();
                    $ya
                        ? DB::table('employee_schedules')->where('id', $p->id ?? null)->orWhere(fn ($q) => $q->where('employee_id', $p->employee_id)->where('schedule_id', $dup->id))->delete()
                        : DB::table('employee_schedules')->where('employee_id', $p->employee_id)->where('schedule_id', $dup->id)->update(['schedule_id' => $principal->id]);
                }

                $dup->delete();
                $filas[] = [$dup->nombre, $dup->company_id, "#{$principal->id} ← #{$dup->id}", $usan, 'unificados en #' . $principal->id . ' (el repetido se borra)'];
            }
        }

        $aplicar ? DB::commit() : DB::rollBack();

        $this->table(['Horario', 'Empresa', 'Horarios', 'Usos', 'Acción'], $filas);
        $this->line($filas ? count($filas) . ' caso(s).' : 'No hay horarios repetidos.');

        return self::SUCCESS;
    }
}

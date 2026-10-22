<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use Illuminate\Console\Command;

/**
 * Registros corregidos a mano (corrección de horas aprobada, registro manual) que guardaron las horas
 * ordinarias por encima del tope de su horario y sin separar las extras (incluye días con permiso aprobado, que quedan
 * bloqueados al aprobarse y conservan lo que se había calculado antes). Recalcula solo ordinarias y
 * extras con las horas de entrada y salida YA guardadas; no toca tardanza, estado ni justificaciones.
 * Simula por defecto.
 */
class RecalcularHorasCorregidas extends Command
{
    protected $signature = 'rrhh:recalcular-horas-corregidas {desde} {hasta} {--aplicar : Guardar (sin esto solo simula)}';
    protected $description = 'Separa horas ordinarias y extra en registros corregidos a mano que pasan del tope de su horario';

    public function handle(AttendanceProcessor $proc): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $this->info($aplicar ? '== APLICANDO ==' : '== SIMULACIÓN (agrega --aplicar para guardar) ==');

        $filas = [];
        $registros = AttendanceRecord::where('corregido_manualmente', true)
            ->whereBetween('fecha', [$this->argument('desde'), $this->argument('hasta')])
            ->whereNotNull('hora_entrada')->whereNotNull('hora_salida')
            ->whereIn('estado', ['presente', 'tarde', 'permiso'])
            ->orderBy('fecha')->get();

        foreach ($registros as $r) {
            $e = Employee::withoutGlobalScopes()->with('schedule')->find($r->employee_id);
            $s = $e?->schedule;
            if (! $e || ! $s || $e->horas_por_marcacion || $e->exonerado_registro) {
                continue;
            }

            $tope = ((strtotime($s->hora_salida) - strtotime($s->hora_entrada))
                - ($s->refrigerio_inicio && $s->refrigerio_fin ? strtotime($s->refrigerio_fin) - strtotime($s->refrigerio_inicio) : 0)) / 3600;

            if ((float) $r->horas_ordinarias <= $tope + 0.02) {
                continue; // ya está dentro del tope: no se toca
            }

            $fecha = $r->fecha instanceof \Carbon\Carbon ? $r->fecha->toDateString() : substr((string) $r->fecha, 0, 10);
            $calc  = $proc->recalcularDesdeHoras($e, $fecha, (string) $r->hora_entrada, (string) $r->hora_salida);

            $filas[] = [
                $fecha, mb_substr("{$e->apellidos} {$e->nombres}", 0, 28),
                "{$r->horas_ordinarias} / {$r->horas_extra_diurnas} / {$r->horas_extra_nocturnas}",
                "{$calc['horas_ordinarias']} / {$calc['horas_extra_diurnas']} / {$calc['horas_extra_nocturnas']}",
            ];

            if ($aplicar) {
                $r->update([
                    'minutos_trabajados'    => $calc['minutos_trabajados'],
                    'horas_ordinarias'      => $calc['horas_ordinarias'],
                    'horas_extra_diurnas'   => $calc['horas_extra_diurnas'],
                    'horas_extra_nocturnas' => $calc['horas_extra_nocturnas'],
                ]);
            }
        }

        $this->table(['Fecha', 'Trabajador', 'Antes (ord / ext25 / ext35)', 'Después'], $filas);
        $this->line($filas ? count($filas) . ' registro(s).' : 'No hay registros corregidos fuera del tope.');

        return self::SUCCESS;
    }
}

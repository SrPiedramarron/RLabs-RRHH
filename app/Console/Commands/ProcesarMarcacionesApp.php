<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\AttendanceRecord;
use App\Models\RemoteCheckin;
use App\Services\AttendanceProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasa por el motor de asistencia (el mismo del reloj) las marcaciones que
 * los trabajadores YA hicieron desde la app antes de que la app calculara
 * tardanza/horas. Por defecto solo SIMULA y muestra qué cambiaría; con
 * --aplicar lo guarda.
 */
class ProcesarMarcacionesApp extends Command
{
    protected $signature = 'asistencia:procesar-marcaciones-app
        {--desde= : Fecha inicial (YYYY-MM-DD)}
        {--hasta= : Fecha final (YYYY-MM-DD), por defecto hoy}
        {--aplicar : Guardar los cambios (sin esto solo simula)}';

    protected $description = 'Calcula tardanza/horas de marcaciones hechas desde la app, igual que las del reloj';

    private const TIPO_LOG = ['entrada' => 0, 'salida' => 1, 'salida_refrigerio' => 4, 'regreso_refrigerio' => 5];

    public function handle(AttendanceProcessor $processor): int
    {
        $desde = $this->option('desde');
        if (! $desde) {
            $this->error('Indica --desde=YYYY-MM-DD');
            return self::FAILURE;
        }
        $hasta  = $this->option('hasta') ?: now()->toDateString();
        $aplicar = (bool) $this->option('aplicar');

        $checkins = RemoteCheckin::with('employee')
            ->where('estado_procesado', 'procesado')
            ->whereDate('fecha_hora', '>=', $desde)
            ->whereDate('fecha_hora', '<=', $hasta)
            ->orderBy('fecha_hora')
            ->get()
            ->filter(fn ($c) => $c->employee && $c->employee->location_id);

        $this->info(($aplicar ? 'APLICANDO' : 'SIMULACIÓN') . " — {$checkins->count()} marcaciones de app entre {$desde} y {$hasta}");

        $cambios = [];
        DB::beginTransaction();

        foreach ($checkins->groupBy(fn ($c) => $c->employee_id . '|' . $c->fecha_hora->toDateString()) as $grupo) {
            $emp     = $grupo->first()->employee;
            $fecha   = $grupo->first()->fecha_hora->toDateString();
            $relojId = (int) ($emp->reloj_id ?: $emp->dni);

            $antes = AttendanceRecord::where('employee_id', $emp->id)->where('fecha', $fecha)->first();
            $antes = $antes ? $antes->only(['minutos_tarde', 'horas_ordinarias', 'horas_extra_diurnas', 'horas_extra_nocturnas', 'estado', 'corregido_manualmente']) : null;

            foreach ($grupo as $c) {
                $existe = AttendanceLog::where('reloj_id', $relojId)->where('timestamp', $c->fecha_hora)->exists();
                if (! $existe) {
                    AttendanceLog::create([
                        'location_id' => $emp->location_id,
                        'reloj_uid'   => 0,
                        'reloj_id'    => $relojId,
                        'timestamp'   => $c->fecha_hora,
                        'tipo'        => self::TIPO_LOG[$c->tipo],
                        'estado'      => 0,
                        'raw_data'    => ['fuente' => 'app', 'remote_checkin_id' => $c->id, 'reproceso' => true],
                        'procesado'   => true,
                        'created_at'  => now(),
                    ]);
                }
            }

            $processor->procesarDia($emp, $fecha, $relojId);

            $despues = AttendanceRecord::where('employee_id', $emp->id)->where('fecha', $fecha)->first();
            if ($despues) {
                $cambios[] = [
                    $fecha,
                    $emp->apellidos . ', ' . $emp->nombres,
                    ($antes['minutos_tarde'] ?? '-') . ' → ' . $despues->minutos_tarde,
                    ($antes['horas_ordinarias'] ?? '-') . ' → ' . $despues->horas_ordinarias,
                    ($antes['estado'] ?? '-') . ' → ' . $despues->estado,
                    ($antes['corregido_manualmente'] ?? false) ? 'corregido a mano (no se toca)' : '',
                ];
            }
        }

        $aplicar ? DB::commit() : DB::rollBack();

        $this->table(['Fecha', 'Trabajador', 'Min. tarde', 'Horas ord.', 'Estado', 'Nota'], $cambios);
        $this->info(count($cambios) . ' días ' . ($aplicar ? 'recalculados y guardados.' : 'se recalcularían (no se guardó nada; usa --aplicar).'));

        return self::SUCCESS;
    }
}

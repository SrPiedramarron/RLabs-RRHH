<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\VacacionHistorial;
use Carbon\Carbon;

/**
 * Jornada ordinaria REFERENCIAL de los trabajadores exonerados de registro
 * (no fiscalizados): como no marcan asistencia, sus horas ordinarias se
 * calculan solas por los días del periodo de corte (26 del mes anterior al
 * 25 del mes), sin registrar jornada por jornada. Pedido de RRHH, oct. 2026:
 *   - Lunes a viernes: 8:30 h por día.
 *   - Sábados: 4:00 h, contando solo 2 sábados al mes.
 * Los tres valores son configurables por trabajador en su ficha.
 *
 * Criterios (no estaban especificados, son los razonables):
 *   - No cuentan los feriados (de la empresa o generales) ni los días de
 *     vacaciones registradas.
 *   - Solo cuentan los días desde su ingreso y hasta su cese, si caen dentro
 *     del periodo.
 *   - Los sábados que cuentan son los primeros N del periodo.
 */
class JornadaReferencialService
{
    /** Minutos ordinarios referenciales del periodo "YYYY-MM". */
    public function minutosPeriodo(Employee $empleado, string $periodo): int
    {
        $fin    = Carbon::parse($periodo . '-25')->startOfDay();
        $inicio = $fin->copy()->subMonthNoOverflow()->addDay()->startOfDay();

        if ($empleado->fecha_ingreso && $empleado->fecha_ingreso->gt($inicio)) {
            $inicio = $empleado->fecha_ingreso->copy()->startOfDay();
        }
        if ($empleado->fecha_cese && $empleado->fecha_cese->lt($fin)) {
            $fin = $empleado->fecha_cese->copy()->startOfDay();
        }
        if ($inicio->gt($fin)) {
            return 0;
        }

        $feriados = Holiday::whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $empleado->company_id))
            ->pluck('fecha')
            ->map(fn ($f) => Carbon::parse((string) $f)->toDateString())
            ->all();

        $vacaciones = VacacionHistorial::where('employee_id', $empleado->id)
            ->where('fecha_inicio', '<=', $fin->toDateString())
            ->where('fecha_fin', '>=', $inicio->toDateString())
            ->get();

        $horasDia      = (float) ($empleado->jornada_ref_horas_dia ?? 8.5);
        $horasSabado   = (float) ($empleado->jornada_ref_horas_sabado ?? 4);
        $sabadosMaximo = (int) ($empleado->jornada_ref_sabados_mes ?? 2);

        $minutos  = 0.0;
        $sabados  = 0;

        for ($dia = $inicio->copy(); $dia->lte($fin); $dia->addDay()) {
            $esFeriado  = in_array($dia->toDateString(), $feriados, true);
            $enVacacion = $vacaciones->contains(fn ($v) => $dia->betweenIncluded($v->fecha_inicio, $v->fecha_fin));
            if ($esFeriado || $enVacacion) {
                continue;
            }

            $dow = $dia->dayOfWeekIso; // 1 lunes ... 7 domingo
            if ($dow <= 5) {
                $minutos += $horasDia * 60;
            } elseif ($dow === 6 && $sabados < $sabadosMaximo) {
                $minutos += $horasSabado * 60;
                $sabados++;
            }
        }

        return (int) round($minutos);
    }
}

<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\VacacionHistorial;
use Carbon\Carbon;

/**
 * Jornada para el archivo .jor de PLAME, con el periodo de corte (26 del mes
 * anterior al 25 del mes). Criterios confirmados por RRHH (oct. 2026):
 *
 * JORNADA REFERENCIAL de trabajadores exonerados de registro (no
 * fiscalizados), que no marcan asistencia:
 *   - Lunes a viernes: 8:30 h por día; sábados: 4:00 h, UN sábado por
 *     quincena calendario (1-15 y 16-fin de mes), o sea 2 al mes.
 *   - Los feriados NO cuentan, y tampoco los días de vacaciones; las
 *     vacaciones se miran por MES CALENDARIO (1 al 30/31), no por el corte.
 *   - Solo cuentan los días desde su ingreso; si cesó, hasta la fecha de
 *     cese aunque pase del 25.
 * Los tres valores (horas L-V, horas sábado, sábados por mes) se configuran
 * por trabajador en su ficha.
 *
 * Para quienes SÍ marcan, ventanaCorte() da el mismo rango (con el cese
 * extendido) para sumar sus horas de asistencia.
 */
class JornadaReferencialService
{
    /**
     * Rango de días del periodo "YYYY-MM": del 26 del mes anterior al 25,
     * recortado al ingreso; si el trabajador cesó, hasta su fecha de cese
     * aunque pase del 25 (mientras sea dentro del mes del periodo).
     *
     * @return array{0: Carbon, 1: Carbon} [inicio, fin]
     */
    public function ventanaCorte(Employee $empleado, string $periodo): array
    {
        $finCorte = Carbon::parse($periodo . '-25')->startOfDay();
        $finMes   = Carbon::parse($periodo . '-01')->endOfMonth()->startOfDay();
        $inicio   = $finCorte->copy()->subMonthNoOverflow()->addDay()->startOfDay();
        $fin      = $finCorte->copy();

        if ($empleado->fecha_ingreso && $empleado->fecha_ingreso->gt($inicio)) {
            $inicio = $empleado->fecha_ingreso->copy()->startOfDay();
        }

        if ($empleado->fecha_cese) {
            $cese = $empleado->fecha_cese->copy()->startOfDay();
            if ($cese->lt($fin) || ($cese->gt($fin) && $cese->lte($finMes))) {
                $fin = $cese;
            }
        }

        return [$inicio, $fin];
    }

    /** Minutos ordinarios referenciales del periodo "YYYY-MM". */
    public function minutosPeriodo(Employee $empleado, string $periodo): int
    {
        [$inicio, $fin] = $this->ventanaCorte($empleado, $periodo);
        if ($inicio->gt($fin)) {
            return 0;
        }

        $inicioMes = Carbon::parse($periodo . '-01')->startOfDay();
        $finMes    = $inicioMes->copy()->endOfMonth()->startOfDay();

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
        $sabadosMaximo = min(2, (int) ($empleado->jornada_ref_sabados_mes ?? 2));

        $minutos         = 0.0;
        $sabadosContados = 0;
        $quincenasUsadas = [];

        for ($dia = $inicio->copy(); $dia->lte($fin); $dia->addDay()) {
            if (in_array($dia->toDateString(), $feriados, true)) {
                continue;
            }

            // Vacaciones: solo los días del mes calendario del periodo.
            $enVacacion = $dia->betweenIncluded($inicioMes, $finMes)
                && $vacaciones->contains(fn ($v) => $dia->betweenIncluded($v->fecha_inicio, $v->fecha_fin));
            if ($enVacacion) {
                continue;
            }

            $dow = $dia->dayOfWeekIso; // 1 lunes ... 7 domingo
            if ($dow <= 5) {
                $minutos += $horasDia * 60;
            } elseif ($dow === 6 && $dia->betweenIncluded($inicioMes, $finMes) && $sabadosContados < $sabadosMaximo) {
                // Un sábado por quincena calendario del mes del periodo.
                $quincena = $dia->day <= 15 ? 1 : 2;
                if (! isset($quincenasUsadas[$quincena])) {
                    $quincenasUsadas[$quincena] = true;
                    $minutos += $horasSabado * 60;
                    $sabadosContados++;
                }
            }
        }

        return (int) round($minutos);
    }
}

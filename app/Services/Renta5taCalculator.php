<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\IngresoHistorico5ta;
use App\Models\ParametroLegal;

/**
 * Corregido tras reunión de RRHH con contabilidad (set. 2026): la versión
 * anterior replicaba "fielmente" un error del Excel manual de InProcess
 * (tramos 3° y 4° sin tope, sin umbral mínimo de exoneración). Confirmado
 * que eso estaba mal — la fórmula correcta es la del método oficial SUNAT
 * (Art. 40 del Reglamento de la LIR): solo tributa el trabajador cuya
 * remuneración bruta mensual proyectada supere S/ 2,700 (o su proyección
 * anual supere 7 UIT), y cada tramo se aplica SOLO al excedente dentro de
 * su propio rango — no a la base completa.
 *
 * Se mantiene la proyección basada en (último sueldo conocido + promedio
 * de comisiones de los últimos 3 meses + gratificaciones pendientes +
 * lo ya percibido en el año) porque eso no fue objetado por RRHH — el
 * problema reportado fue específicamente el umbral y el tope de tramos.
 */
class Renta5taCalculator
{
    // Umbral mínimo de exoneración: por debajo de esto no hay retención,
    // ni mensual ni proyectada (RRHH, set. 2026).
    const UMBRAL_MENSUAL_EXONERADO = 2700.0;
    const UIT_EXONERADAS = 7;

    // Tramos del impuesto, en múltiplos de UIT (método oficial SUNAT,
    // Art. 53 LIR): hasta 5 UIT 8%, de 5 a 20 UIT 14%, de 20 a 35 UIT 17%,
    // más de 35 UIT 20%. Antes estaban hardcodeados en soles (27500,
    // 110000, 192500 = 5/20/35 × UIT 5500) — ahora se recalculan solos
    // según la UIT vigente (Administración > Parámetros Legales), oct. 2026.
    const TRAMOS_UIT = [
        ['desde' => 0,  'hasta' => 5,  'tasa' => 0.08],
        ['desde' => 5,  'hasta' => 20, 'tasa' => 0.14],
        ['desde' => 20, 'hasta' => 35, 'tasa' => 0.17],
        ['desde' => 35, 'hasta' => null, 'tasa' => 0.20],
    ];

    /** Otras rentas afectas del trabajador (otro empleador) para el ejercicio; 0 si son de otro año. */
    private function otrasRentas(Employee $empleado, int $year): float
    {
        $monto = (float) ($empleado->otras_rentas_afectas ?? 0);
        $anio  = $empleado->otras_rentas_anio;

        return ($monto > 0 && ($anio === null || (int) $anio === $year)) ? $monto : 0.0;
    }

    /** Retenciones de 5ta de otros empleadores para el ejercicio; 0 si son de otro año. */
    private function otrasRetenciones(Employee $empleado, int $year): float
    {
        $monto = (float) ($empleado->otras_retenciones_5ta ?? 0);
        $anio  = $empleado->otras_rentas_anio;

        return ($monto > 0 && ($anio === null || (int) $anio === $year)) ? $monto : 0.0;
    }

    private function uit($fecha = null): float
    {
        return ParametroLegal::valor(ParametroLegal::UIT, $fecha);
    }

    const CONCEPTO_SUELDO      = 'SUELDO + ASIG FAM';
    const CONCEPTO_COMISIONES  = 'COMISIONES';
    const CONCEPTO_UTILIDADES  = 'UTILIDADES';
    const CONCEPTO_RETENCION   = 'RETENCION_5TA_APLICADA';

    public function calcular(
        Employee $empleado,
        ?float $comisionesMesActual,
        int $year,
        int $month,
    ): array {
        // Sin comisiones del mes (p. ej. la quincena del día 15) se estima con las últimas 3
        // comisiones registradas, igual que la hoja de RRHH.
        $periodoActual   = sprintf('%04d-%02d', $year, $month);
        $mesesRestantes  = 13 - $month; // incluye el mes actual
        $fechaRef        = \Carbon\Carbon::create($year, $month, 1);
        $uit             = $this->uit($fechaRef);

        // ── Último sueldo conocido (mes anterior) ───────────────────────────
        $periodoAnterior = sprintf('%04d-%02d', $month === 1 ? $year - 1 : $year, $month === 1 ? 12 : $month - 1);
        $ultimoSueldo    = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', $periodoAnterior)
            ->where('concepto', self::CONCEPTO_SUELDO)
            ->value('monto');

        // Si el mes anterior aún no está cargado, el último sueldo conocido del año.
        if ($ultimoSueldo <= 0) {
            $ultimoSueldo = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
                ->where('periodo', '<', $periodoActual)->where('periodo', 'like', $year . '-%')
                ->where('concepto', self::CONCEPTO_SUELDO)
                ->orderByDesc('periodo')->value('monto');
        }

        if ($ultimoSueldo <= 0) {
            // No hay histórico del mes anterior (ej. el propio sistema ya
            // lo calculó) — usar sueldo_base + asignación familiar (si
            // aplica) como aproximación, consistente con el concepto
            // combinado "SUELDO + ASIG FAM" que trae el histórico importado.
            // Asignación familiar = 10% de la RMV vigente (Administración >
            // Parámetros Legales), mismo cálculo que usa PlanillaService.
            $ultimoSueldo = floatval($empleado->sueldo_base)
                + ($empleado->aplica_asignacion_familiar ? round(ParametroLegal::valor(ParametroLegal::RMV, $fechaRef) * 0.10, 2) : 0);
        }

        // ── Promedio de comisiones de los últimos 3 meses (incluye el actual) ──
        if ($comisionesMesActual === null) {
            // Últimas 3 comisiones ya registradas (del año), sin contar meses aún vacíos.
            $ultimas = IngresoHistorico5ta::where('employee_id', $empleado->id)
                ->where('periodo', '<', $periodoActual)->where('periodo', 'like', $year . '-%')
                ->where('concepto', self::CONCEPTO_COMISIONES)->where('monto', '>', 0)
                ->orderByDesc('periodo')->limit(3)->pluck('monto');
            $promComisiones3Ult = $ultimas->sum() / 3;
        } else {
            $comisionesPrevias = [];
            for ($i = 1; $i <= 2; $i++) {
                $m = $month - $i;
                $y = $year;
                if ($m <= 0) { $m += 12; $y -= 1; }
                $periodo = sprintf('%04d-%02d', $y, $m);

                $comisionesPrevias[] = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
                    ->where('periodo', $periodo)
                    ->where('concepto', self::CONCEPTO_COMISIONES)
                    ->value('monto');
            }
            $promComisiones3Ult = ($comisionesMesActual + array_sum($comisionesPrevias)) / 3;
        }

        $ssPromCom = $ultimoSueldo + $promComisiones3Ult;

        // ── Gratificaciones pendientes este año (julio y/o diciembre) ───────
        // CORREGIDO (verificado contra archivo real de agosto): se mantienen
        // 2 gratificaciones en la proyección durante casi todo el año, NO
        // se reduce a 1 después de julio como se asumió originalmente.
        // Diciembre sin confirmar todavía — se deja en 0 por precaución
        // hasta tener evidencia real de ese mes.
        // Hoja de RRHH para vendedores (oct. 2026): pasada la gratificación de julio, que ya está
        // dentro de lo percibido, solo falta la de diciembre.
        $gratificacionesPendientes = match (true) {
            $month <= 7  => 2,
            $month === 12 => 0,
            default       => 1,
        };

        // ── Total ya percibido este año, ANTES del mes actual ──
        // Las utilidades solo cuentan si no se les retuvo aparte (RENTA UTILIDAD).
        $retuvoUtilidadesAparte = IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', 'like', $year . '-%')->where('concepto', 'RENTA UTILIDAD')->where('monto', '>', 0)->exists();
        $excluidos = [self::CONCEPTO_RETENCION, 'RENTA UTILIDAD', 'BASE_PLAME_5TA'];
        if ($retuvoUtilidadesAparte) {
            $excluidos[] = self::CONCEPTO_UTILIDADES;
        }
        $totalPercibido = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', '<', $periodoActual)
            ->where('periodo', 'like', $year . '-%')
            ->whereNotIn('concepto', $excluidos)
            ->sum('monto');

        $otrasRentas = $this->otrasRentas($empleado, $year);

        $proyeccionAlMes = ($ssPromCom * $mesesRestantes)
            + ($ssPromCom * 1.09 * $gratificacionesPendientes)
            + $totalPercibido
            + $otrasRentas;

        $totalRemuneraciones = $proyeccionAlMes;

        $brutoMesActual = $ultimoSueldo + ($comisionesMesActual ?? $promComisiones3Ult);
        $exonerado = $this->estaExonerado($brutoMesActual, $totalRemuneraciones, $uit);

        $baseImponible = $exonerado ? 0.0 : max(0, $totalRemuneraciones - (self::UIT_EXONERADAS * $uit));

        $impuestoAnual = $this->aplicarTramos($baseImponible, $uit);

        $retencionesPrevias = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', '<', $periodoActual)
            ->where('periodo', 'like', $year . '-%')
            ->where('concepto', self::CONCEPTO_RETENCION)
            ->sum('monto');

        $otrasRetenciones = $this->otrasRetenciones($empleado, $year);
        $saldoPorRetener = max(0, $impuestoAnual - $retencionesPrevias - $otrasRetenciones);
        $cuotaMensual     = round($saldoPorRetener / $mesesRestantes, 2);

        return [
            'cuota_mensual'             => $cuotaMensual,
            'ultimo_sueldo'             => round($ultimoSueldo, 2),
            'promedio_comisiones_3m'    => round($promComisiones3Ult, 2),
            'ss_prom_com'               => round($ssPromCom, 2),
            'gratificaciones_pendientes' => $gratificacionesPendientes,
            'total_percibido'           => round($totalPercibido, 2),
            'proyeccion_al_mes'         => round($proyeccionAlMes, 2),
            'otras_rentas'              => round($otrasRentas, 2),
            'base_imponible'            => round($baseImponible, 2),
            'impuesto_anual'            => round($impuestoAnual, 2),
            'retenciones_previas'       => round($retencionesPrevias, 2),
            'otras_retenciones'         => round($otrasRetenciones, 2),
            'saldo_por_retener'         => round($saldoPorRetener, 2),
            'divisor'                   => $mesesRestantes,
        ];
    }

    /**
     * Fórmula para empleados SIN comisiones (hoja "RTA 5TA" de InProcess).
     * Estructura distinta a la de vendedores: anualiza el sueldo actual
     * directo (×12), y suma los conceptos variables (movilidad, horas
     * extra, bono) YA PERCIBIDOS este año, INCLUYENDO el mes actual (a
     * diferencia de vendedores, que excluye el mes actual).
     *
     * Verificado exacto contra Fiorella Lobo, junio 2026 (match S/0.00).
     */
    public function calcularNoComisionado(
        Employee $empleado,
        float $sueldoMesActual,
        int $year,
        int $month,
    ): array {
        $periodoActual  = sprintf('%04d-%02d', $year, $month);
        $mesesRestantes = 13 - $month;
        $uit            = $this->uit(\Carbon\Carbon::create($year, $month, 1));

        // CORREGIDO (verificado contra archivo real de agosto): se
        // mantienen 2 gratificaciones en la proyección durante casi todo
        // el año. Diciembre sin confirmar, se deja en 0 por precaución.
        $gratificacionesPendientes = ($month === 12) ? 0 : 2;

        $gratificacionUnitaria = $sueldoMesActual * 1.09;

        $conceptosExcluidos = [
            self::CONCEPTO_SUELDO,
            self::CONCEPTO_UTILIDADES,
            self::CONCEPTO_RETENCION,
            'GRATIFICACION', 'GRATIFICACION JULIO', 'GRATIFICACION DICIEMBRE',
            'COMPRA DE VACACIONES', 'VALE CONSUMO',
            'BASE_PLAME_5TA',
        ];

        $sumaVariablesYTD = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', '<=', $periodoActual)
            ->where('periodo', 'like', $year . '-%')
            ->whereNotIn('concepto', $conceptosExcluidos)
            ->sum('monto');

        // Meses anteriores al actual: sueldo realmente pagado (histórico importado; si ese mes
        // no está en el histórico, la planilla del sistema) y, para esos mismos meses sin
        // histórico, los demás ingresos variables (movilidad, bonos, horas extra).
        [$sueldosPasados, $variablesPlanilla] = $this->ingresosMesesAnteriores($empleado, $year, $month, $sueldoMesActual);
        $sumaVariablesYTD += $variablesPlanilla;

        // Utilidades: si ya se les retuvo aparte (RENTA UTILIDAD), no entran a la base anual;
        // si no hubo retención aparte, forman parte de la renta del año.
        $utilidades = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', 'like', $year . '-%')
            ->where('concepto', self::CONCEPTO_UTILIDADES)
            ->sum('monto');
        $retencionUtilidades = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', 'like', $year . '-%')
            ->where('concepto', 'RENTA UTILIDAD')
            ->sum('monto');
        $utilidadesEnBase = $retencionUtilidades > 0 ? 0.0 : $utilidades;

        // Quien ingresó durante el año no cobra 12 meses ni las dos gratificaciones
        // completas: se proyectan solo los meses (y semestres) efectivamente trabajados.
        [$mesesDelAnio, $gratificacionesDelAnio] = $this->proporcionAnioTrabajado($empleado, $year);

        // Meses restantes (el actual incluido) al sueldo actual. Si ingresó este año, los
        // meses de antes del ingreso no cuentan y el primer mes va proporcional.
        $mesesFuturos = $mesesRestantes;
        $ingreso = $empleado->fecha_ingreso;
        if ($ingreso && $ingreso->year === $year && $ingreso->month === $month) {
            $mesesFuturos = $mesesRestantes - 1 + ($ingreso->daysInMonth - $ingreso->day + 1) / $ingreso->daysInMonth;
        } elseif ($ingreso && $ingreso->year === $year && $ingreso->month > $month) {
            $mesesFuturos = (12 - $ingreso->month) + ($ingreso->daysInMonth - $ingreso->day + 1) / $ingreso->daysInMonth;
        }

        $totalRemuneraciones = $sueldosPasados
            + ($sueldoMesActual * $mesesFuturos)
            + ($gratificacionUnitaria * ($gratificacionesPendientes > 0 ? $gratificacionesDelAnio : 0))
            + $sumaVariablesYTD
            + $utilidadesEnBase
            + $this->otrasRentas($empleado, $year);

        $exonerado = $this->estaExonerado($sueldoMesActual, $totalRemuneraciones, $uit);

        $baseImponible = $exonerado ? 0.0 : max(0, $totalRemuneraciones - (self::UIT_EXONERADAS * $uit));

        $impuestoAnual = $this->aplicarTramos($baseImponible, $uit);

        $retencionesPrevias = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', '<', $periodoActual)
            ->where('periodo', 'like', $year . '-%')
            ->where('concepto', self::CONCEPTO_RETENCION)
            ->sum('monto');

        $otrasRetenciones = $this->otrasRetenciones($empleado, $year);
        $saldoPorRetener = max(0, $impuestoAnual - $retencionesPrevias - $otrasRetenciones);
        $cuotaMensual     = round($saldoPorRetener / $mesesRestantes, 2);

        return [
            'cuota_mensual'              => $cuotaMensual,
            'sueldo_mes_actual'          => round($sueldoMesActual, 2),
            'gratificacion_unitaria'     => round($gratificacionUnitaria, 2),
            'gratificaciones_pendientes' => $gratificacionesPendientes,
            'suma_variables_ytd'         => round($sumaVariablesYTD, 2),
            'sueldos_pasados'            => round($sueldosPasados, 2),
            'utilidades_en_base'         => round($utilidadesEnBase, 2),
            'otras_rentas'               => round($this->otrasRentas($empleado, $year), 2),
            'total_remuneraciones'       => round($totalRemuneraciones, 2),
            'base_imponible'             => round($baseImponible, 2),
            'impuesto_anual'             => round($impuestoAnual, 2),
            'retenciones_previas'        => round($retencionesPrevias, 2),
            'otras_retenciones'          => round($otrasRetenciones, 2),
            'saldo_por_retener'          => round($saldoPorRetener, 2),
            'divisor'                    => $mesesRestantes,
        ];
    }

    /**
     * Sueldos de los meses anteriores al actual (del año) y variables de los meses que
     * no están en el histórico importado.
     *
     * @return array{0: float, 1: float} [sueldos pasados, variables tomadas de planilla]
     */
    private function ingresosMesesAnteriores(Employee $empleado, int $year, int $month, float $sueldoActual): array
    {
        $ingreso = $empleado->fecha_ingreso;
        $sueldos = 0.0;
        $variables = 0.0;

        for ($m = 1; $m < $month; $m++) {
            if ($ingreso && $ingreso->year === $year && $m < $ingreso->month) {
                continue; // aún no trabajaba
            }

            $periodo = sprintf('%04d-%02d', $year, $m);

            $histSueldo = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
                ->where('periodo', $periodo)->where('concepto', self::CONCEPTO_SUELDO)->sum('monto');

            if ($histSueldo > 0) {
                $sueldos += $histSueldo;
                continue;
            }

            $liq = \App\Models\PlanillaLiquidacion::where('employee_id', $empleado->id)->where('periodo', $periodo)->first();

            if ($liq && ((float) $liq->sueldo_proporcional + (float) $liq->asignacion_familiar) > 0) {
                $sueldos += (float) $liq->sueldo_proporcional + (float) $liq->asignacion_familiar;
                // Variables de ese mes solo si el histórico no trae nada de ese mes.
                $hayHistorico = IngresoHistorico5ta::where('employee_id', $empleado->id)->where('periodo', $periodo)->exists();
                if (! $hayHistorico) {
                    $variables += (float) $liq->bono_movilidad + (float) $liq->bono_encargatura + (float) $liq->bonos_especiales
                        + (float) $liq->importe_horas_extra_diurnas + (float) $liq->importe_horas_extra_nocturnas;
                }
            } else {
                $sueldos += $sueldoActual; // sin datos de ese mes: se asume el sueldo actual
            }
        }

        return [$sueldos, $variables];
    }
    /**
     * Meses del año en que el trabajador efectivamente trabaja y gratificaciones
     * (julio y diciembre) que le corresponden, prorrateadas por meses del
     * semestre. Quien ingresó antes del año: 12 meses y 2 gratificaciones.
     *
     * @return array{0: float, 1: float} [meses del año, gratificaciones del año]
     */
    private function proporcionAnioTrabajado(Employee $empleado, int $year): array
    {
        $ingreso = $empleado->fecha_ingreso;

        if (! $ingreso || $ingreso->year < $year) {
            return [12.0, 2.0];
        }
        if ($ingreso->year > $year) {
            return [0.0, 0.0];
        }

        $mes     = $ingreso->month;
        $primer  = ($ingreso->daysInMonth - $ingreso->day + 1) / $ingreso->daysInMonth; // fracción del primer mes

        $meses     = (12 - $mes) + $primer;
        $semestre1 = $mes <= 6 ? (6 - $mes) + $primer : 0.0;       // enero-junio
        $semestre2 = $mes <= 6 ? 6.0 : (12 - $mes) + $primer;      // julio-diciembre

        return [$meses, min(6, $semestre1) / 6 + min(6, $semestre2) / 6];
    }
    /**
     * Cada tramo tributa SOLO su excedente dentro de su propio rango
     * (método oficial). Corregido: antes los tramos 3° y 4° no tenían
     * tope y calculaban sobre la base completa en vez de su excedente,
     * lo que sobre-retenía a los sueldos altos.
     */
    private function aplicarTramos(float $baseImponible, float $uit): float
    {
        $limite1 = 5 * $uit;
        $limite2 = 20 * $uit;
        $limite3 = 35 * $uit;

        $tramo1 = min($baseImponible, $limite1) * self::TRAMOS_UIT[0]['tasa'];
        $tramo2 = min(max(0, $baseImponible - $limite1), $limite2 - $limite1) * self::TRAMOS_UIT[1]['tasa'];
        $tramo3 = min(max(0, $baseImponible - $limite2), $limite3 - $limite2) * self::TRAMOS_UIT[2]['tasa'];
        $tramo4 = max(0, $baseImponible - $limite3) * self::TRAMOS_UIT[3]['tasa'];

        return $tramo1 + $tramo2 + $tramo3 + $tramo4;
    }

    /**
     * Umbral de exoneración (RRHH, set. 2026): si ni el sueldo bruto del
     * mes actual ni la proyección anual superan el mínimo, no hay
     * retención — se evita correr toda la proyección para quien
     * claramente no debería tributar.
     */
    private function estaExonerado(float $brutoMesActual, float $totalRemuneracionesProyectadas, float $uit): bool
    {
        return $brutoMesActual <= self::UMBRAL_MENSUAL_EXONERADO
            && $totalRemuneracionesProyectadas <= (self::UIT_EXONERADAS * $uit);
    }
}

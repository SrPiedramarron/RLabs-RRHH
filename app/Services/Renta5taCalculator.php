<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\IngresoHistorico5ta;

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
    const UIT_2026 = 5500; // DS N° 301-2025-EF

    // Umbral mínimo de exoneración: por debajo de esto no hay retención,
    // ni mensual ni proyectada (RRHH, set. 2026).
    const UMBRAL_MENSUAL_EXONERADO = 2700.0;
    const UIT_EXONERADAS = 7;

    const TRAMOS = [
        ['desde' => 0,      'hasta' => 27500,  'tasa' => 0.08],
        ['desde' => 27500,  'hasta' => 110000, 'tasa' => 0.14],
        ['desde' => 110000, 'hasta' => 192500, 'tasa' => 0.17],
        ['desde' => 192500, 'hasta' => null,   'tasa' => 0.20],
    ];

    const CONCEPTO_SUELDO      = 'SUELDO + ASIG FAM';
    const CONCEPTO_COMISIONES  = 'COMISIONES';
    const CONCEPTO_UTILIDADES  = 'UTILIDADES';
    const CONCEPTO_RETENCION   = 'RETENCION_5TA_APLICADA';

    public function calcular(
        Employee $empleado,
        float $comisionesMesActual,
        int $year,
        int $month,
    ): array {
        $periodoActual   = sprintf('%04d-%02d', $year, $month);
        $mesesRestantes  = 13 - $month; // incluye el mes actual

        // ── Último sueldo conocido (mes anterior) ───────────────────────────
        $periodoAnterior = sprintf('%04d-%02d', $month === 1 ? $year - 1 : $year, $month === 1 ? 12 : $month - 1);
        $ultimoSueldo    = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', $periodoAnterior)
            ->where('concepto', self::CONCEPTO_SUELDO)
            ->value('monto');

        if ($ultimoSueldo <= 0) {
            // No hay histórico del mes anterior (ej. el propio sistema ya
            // lo calculó) — usar sueldo_base + asignación familiar (si
            // aplica) como aproximación, consistente con el concepto
            // combinado "SUELDO + ASIG FAM" que trae el histórico importado.
            // NOTA: 113 = RMV_2026 (1130) × 10%, mismo cálculo que usa
            // PlanillaService — si cambia la RMV, actualizar aquí también.
            $ultimoSueldo = floatval($empleado->sueldo_base) + ($empleado->aplica_asignacion_familiar ? 113.0 : 0);
        }

        // ── Promedio de comisiones de los últimos 3 meses (incluye el actual) ──
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

        $ssPromCom = $ultimoSueldo + $promComisiones3Ult;

        // ── Gratificaciones pendientes este año (julio y/o diciembre) ───────
        // CORREGIDO (verificado contra archivo real de agosto): se mantienen
        // 2 gratificaciones en la proyección durante casi todo el año, NO
        // se reduce a 1 después de julio como se asumió originalmente.
        // Diciembre sin confirmar todavía — se deja en 0 por precaución
        // hasta tener evidencia real de ese mes.
        $gratificacionesPendientes = ($month === 12) ? 0 : 2;

        // ── Total ya percibido este año, ANTES del mes actual (SIN utilidades) ──
        $totalPercibido = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', '<', $periodoActual)
            ->where('periodo', 'like', $year . '-%')
            ->whereNotIn('concepto', [self::CONCEPTO_RETENCION, self::CONCEPTO_UTILIDADES])
            ->sum('monto');

        $proyeccionAlMes = ($ssPromCom * $mesesRestantes)
            + ($ssPromCom * 1.09 * $gratificacionesPendientes)
            + $totalPercibido;

        $totalRemuneraciones = $proyeccionAlMes;

        $brutoMesActual = $ultimoSueldo + $comisionesMesActual;
        $exonerado = $this->estaExonerado($brutoMesActual, $totalRemuneraciones);

        $baseImponible = $exonerado ? 0.0 : max(0, $totalRemuneraciones - (self::UIT_EXONERADAS * self::UIT_2026));

        $impuestoAnual = $this->aplicarTramos($baseImponible);

        $retencionesPrevias = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', '<', $periodoActual)
            ->where('periodo', 'like', $year . '-%')
            ->where('concepto', self::CONCEPTO_RETENCION)
            ->sum('monto');

        $saldoPorRetener = max(0, $impuestoAnual - $retencionesPrevias);
        $cuotaMensual     = round($saldoPorRetener / $mesesRestantes, 2);

        return [
            'cuota_mensual'             => $cuotaMensual,
            'ultimo_sueldo'             => round($ultimoSueldo, 2),
            'promedio_comisiones_3m'    => round($promComisiones3Ult, 2),
            'ss_prom_com'               => round($ssPromCom, 2),
            'gratificaciones_pendientes' => $gratificacionesPendientes,
            'total_percibido'           => round($totalPercibido, 2),
            'proyeccion_al_mes'         => round($proyeccionAlMes, 2),
            'base_imponible'            => round($baseImponible, 2),
            'impuesto_anual'            => round($impuestoAnual, 2),
            'retenciones_previas'       => round($retencionesPrevias, 2),
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
        ];

        $sumaVariablesYTD = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', '<=', $periodoActual)
            ->where('periodo', 'like', $year . '-%')
            ->whereNotIn('concepto', $conceptosExcluidos)
            ->sum('monto');

        $totalRemuneraciones = ($sueldoMesActual * 12)
            + ($gratificacionUnitaria * $gratificacionesPendientes)
            + $sumaVariablesYTD;

        $exonerado = $this->estaExonerado($sueldoMesActual, $totalRemuneraciones);

        $baseImponible = $exonerado ? 0.0 : max(0, $totalRemuneraciones - (self::UIT_EXONERADAS * self::UIT_2026));

        $impuestoAnual = $this->aplicarTramos($baseImponible);

        $retencionesPrevias = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', '<', $periodoActual)
            ->where('periodo', 'like', $year . '-%')
            ->where('concepto', self::CONCEPTO_RETENCION)
            ->sum('monto');

        $saldoPorRetener = max(0, $impuestoAnual - $retencionesPrevias);
        $cuotaMensual     = round($saldoPorRetener / $mesesRestantes, 2);

        return [
            'cuota_mensual'              => $cuotaMensual,
            'sueldo_mes_actual'          => round($sueldoMesActual, 2),
            'gratificacion_unitaria'     => round($gratificacionUnitaria, 2),
            'gratificaciones_pendientes' => $gratificacionesPendientes,
            'suma_variables_ytd'         => round($sumaVariablesYTD, 2),
            'total_remuneraciones'       => round($totalRemuneraciones, 2),
            'base_imponible'             => round($baseImponible, 2),
            'impuesto_anual'             => round($impuestoAnual, 2),
            'retenciones_previas'        => round($retencionesPrevias, 2),
            'saldo_por_retener'          => round($saldoPorRetener, 2),
            'divisor'                    => $mesesRestantes,
        ];
    }

    /**
     * Cada tramo tributa SOLO su excedente dentro de su propio rango
     * (método oficial). Corregido: antes los tramos 3° y 4° no tenían
     * tope y calculaban sobre la base completa en vez de su excedente,
     * lo que sobre-retenía a los sueldos altos.
     */
    private function aplicarTramos(float $baseImponible): float
    {
        $tramo1 = min($baseImponible, 27500) * self::TRAMOS[0]['tasa'];
        $tramo2 = min(max(0, $baseImponible - 27500), 110000 - 27500) * self::TRAMOS[1]['tasa'];
        $tramo3 = min(max(0, $baseImponible - 110000), 192500 - 110000) * self::TRAMOS[2]['tasa'];
        $tramo4 = max(0, $baseImponible - 192500) * self::TRAMOS[3]['tasa'];

        return $tramo1 + $tramo2 + $tramo3 + $tramo4;
    }

    /**
     * Umbral de exoneración (RRHH, set. 2026): si ni el sueldo bruto del
     * mes actual ni la proyección anual superan el mínimo, no hay
     * retención — se evita correr toda la proyección para quien
     * claramente no debería tributar.
     */
    private function estaExonerado(float $brutoMesActual, float $totalRemuneracionesProyectadas): bool
    {
        return $brutoMesActual <= self::UMBRAL_MENSUAL_EXONERADO
            && $totalRemuneracionesProyectadas <= (self::UIT_EXONERADAS * self::UIT_2026);
    }
}

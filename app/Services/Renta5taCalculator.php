<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\IngresoHistorico5ta;

/**
 * Replica FIEL de la fórmula real que usa InProcess en su Excel manual
 * (hoja "RTA 5TA" / "RTA 5TA VTAS"), verificada celda por celda contra el
 * archivo real de junio 2026 — NO es el método "oficial" simplificado de
 * SUNAT (Art. 40), es específicamente cómo InProcess calcula hoy, según
 * confirmó Ricardo con contabilidad (ago 2026).
 *
 * Diferencias clave frente al método oficial genérico:
 *  - Proyecta usando (último sueldo conocido + promedio de comisiones de
 *    los últimos 3 meses incluyendo el actual), no el sueldo bruto del
 *    mes actual tal cual.
 *  - Suma DOS gratificaciones futuras (julio y diciembre) con un 9% extra
 *    (Ley 29351) mientras no se hayan pagado todavía ese año.
 *  - Excluye UTILIDADES del cálculo (tienen tratamiento tributario aparte).
 *  - Divide el saldo pendiente entre los MESES RESTANTES del año (no usa
 *    la tabla oficial de fraccionamiento 12/9/8/5/4/1).
 *  - Los tramos 3° y 4° NO están topados en su fórmula (posible ajuste
 *    vía código PLAME 0928 "Devolución exceso retención 5ta" en diciembre
 *    — replicado tal cual, sin "corregir" su comportamiento).
 */
class Renta5taCalculator
{
    const UIT_2026 = 5500; // DS N° 301-2025-EF

    const TRAMOS = [
        ['desde' => 0,      'hasta' => 27500,  'tasa' => 0.08],
        ['desde' => 27500,  'hasta' => 110000, 'tasa' => 0.14],
        ['desde' => 110000, 'hasta' => 192500, 'tasa' => 0.17], // SIN TOPE en la fórmula real (ver nota arriba)
        ['desde' => 192500, 'hasta' => null,   'tasa' => 0.20], // "hasta 45 UIT" según label, sin tope real en la fórmula
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
        $baseImponible        = max(0, $totalRemuneraciones - (7 * self::UIT_2026));

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
     * Replica EXACTA de las 4 fórmulas de tramo del Excel — incluyendo que
     * el 3° y 4° tramo NO están topados (restan desde su umbral hasta la
     * base imponible completa, sin límite superior propio).
     */
    /**
     * Réplica fiel de cómo InProcess arma esto en su Excel — verificado
     * contra DOS casos reales (Ernesto, alto ingreso; Fiorella, bajo
     * ingreso), match exacto en ambos:
     *
     *  - Tramo 1 y 2: TOPADOS a su propio rango (marginal correcto) —
     *    si la base no los alcanza, dan 0 o un monto parcial; si los
     *    supera completamente, dan su monto fijo máximo.
     *  - Tramo 3 y 4: SIN TOPE — se calculan como (base - piso) × tasa,
     *    usando la base COMPLETA, no topados a su propio rango. Esto
     *    genera un "solape" real en su fórmula para ingresos muy altos
     *    (posible ajuste vía código PLAME 0928 en diciembre) — replicado
     *    tal cual porque así es como InProcess calcula hoy, confirmado
     *    con el caso real de Ernesto.
     */
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

        $baseImponible = max(0, $totalRemuneraciones - (7 * self::UIT_2026));

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

    private function aplicarTramos(float $baseImponible): float
    {
        $tramo1 = min($baseImponible, 27500) * self::TRAMOS[0]['tasa'];
        $tramo2 = min(max(0, $baseImponible - 27500), 110000 - 27500) * self::TRAMOS[1]['tasa'];
        $tramo3 = max(0, $baseImponible - 110000) * self::TRAMOS[2]['tasa'];  // SIN TOPE
        $tramo4 = max(0, $baseImponible - 192500) * self::TRAMOS[3]['tasa'];  // SIN TOPE

        return $tramo1 + $tramo2 + $tramo3 + $tramo4;
    }
}

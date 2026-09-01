<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\IngresoHistorico5ta;

class Renta5taCalculator
{
    const UIT_2026 = 5500; // DS N° 301-2025-EF

    /**
     * Tramos confirmados por contabilidad de InProcess (ago 2026) — NO son
     * los oficiales de SUNAT (Art. 53 LIR, que son 15%/21%/30% sobre 27/54
     * UIT). Ricardo confirmó usar esta tabla específica tras conversar con
     * contabilidad. Si esto cambia, ajustar aquí.
     *
     * ⚠️ Sin definir: qué tasa aplica por encima de 35 UIT (el Excel de
     * InProcess no tiene un cuarto tramo). Se extrapola 17% indefinido —
     * confirmar con contabilidad si algún trabajador se acerca a ese nivel.
     */
    const TRAMOS = [
        ['hasta' => 27500,  'tasa' => 0.08],  // hasta 5 UIT
        ['hasta' => 110000, 'tasa' => 0.14],  // exceso 5-20 UIT
        ['hasta' => 192500, 'tasa' => 0.17],  // exceso 20-35 UIT
        ['hasta' => null,   'tasa' => 0.17],  // exceso 35 UIT — SIN CONFIRMAR, asumido igual al tramo anterior
    ];

    /**
     * Calcula la retención de 5ta categoría del mes usando el método real
     * acumulado/proyectado (equivalente al Art. 40 del Reglamento LIR, pero
     * con los tramos propios de InProcess).
     *
     * @param float $remuneracionBrutaMesActual Base imponible del mes actual (bruto + bono movilidad si aplica)
     */
    public function calcular(
        Employee $empleado,
        float $remuneracionBrutaMesActual,
        int $year,
        int $month,
    ): array {
        $periodoActual = sprintf('%04d-%02d', $year, $month);
        $mesesRestantes = 13 - $month; // incluye el mes actual

        // Ingresos ya pagados este año, ANTES del periodo actual (histórico
        // importado + lo que el propio sistema ya calculó en meses previos,
        // si los hubiera — hoy no aplica porque agosto es el primer mes).
        $ingresosYaPagados = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', '<', $periodoActual)
            ->where('periodo', 'like', $year . '-%')
            ->where('concepto', '!=', 'RETENCION_5TA_APLICADA')
            ->sum('monto');

        $baseAnualProyectada = ($remuneracionBrutaMesActual * $mesesRestantes) + $ingresosYaPagados;
        $baseImponible        = max(0, $baseAnualProyectada - (7 * self::UIT_2026));

        $impuestoAnual = $this->aplicarTramos($baseImponible);

        $retencionesPrevias = (float) IngresoHistorico5ta::where('employee_id', $empleado->id)
            ->where('periodo', '<', $periodoActual)
            ->where('periodo', 'like', $year . '-%')
            ->where('concepto', 'RETENCION_5TA_APLICADA')
            ->sum('monto');

        $saldoPorRetener = max(0, $impuestoAnual - $retencionesPrevias);

        $divisor = match (true) {
            $month <= 3  => 12,
            $month === 4 => 9,
            $month <= 7  => 8,
            $month === 8 => 5,
            $month <= 11 => 4,
            default      => 1, // diciembre: regularización total, sin fraccionar
        };

        $cuotaMensual = round($saldoPorRetener / $divisor, 2);

        return [
            'cuota_mensual'          => $cuotaMensual,
            'base_anual_proyectada'  => round($baseAnualProyectada, 2),
            'ingresos_ya_pagados'    => round($ingresosYaPagados, 2),
            'base_imponible'         => round($baseImponible, 2),
            'impuesto_anual'         => round($impuestoAnual, 2),
            'retenciones_previas'    => round($retencionesPrevias, 2),
            'saldo_por_retener'      => round($saldoPorRetener, 2),
            'divisor'                => $divisor,
        ];
    }

    private function aplicarTramos(float $baseImponible): float
    {
        $impuesto     = 0.0;
        $limiteAnterior = 0.0;

        foreach (self::TRAMOS as $tramo) {
            $limite = $tramo['hasta'] ?? PHP_FLOAT_MAX;

            if ($baseImponible <= $limiteAnterior) {
                break;
            }

            $montoEnTramo = min($baseImponible, $limite) - $limiteAnterior;
            $impuesto += $montoEnTramo * $tramo['tasa'];
            $limiteAnterior = $limite;
        }

        return $impuesto;
    }
}

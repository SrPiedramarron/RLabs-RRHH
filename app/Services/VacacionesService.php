<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;

class VacacionesService
{
    /** Días que se acumulan por cada mes completo trabajado. */
    private const DIAS_POR_MES = 2.5;

    /**
     * Calcula el saldo de vacaciones por tomar de un trabajador.
     *
     * Regla acordada con RRHH (reemplaza la lógica anterior basada en
     * snapshot de saldo inicial cargado por Excel):
     *
     *   - Si el trabajador YA registró una vacación en el sistema
     *     (fecha_ultima_vacacion no es null), el conteo de DÍAS NUEVOS
     *     arranca desde esa fecha — es decir, desde que "volvió" de su
     *     última vacación tomada.
     *   - Si NUNCA ha tomado vacaciones en el sistema, el conteo arranca
     *     desde su fecha_ingreso.
     *   - A esos días nuevos se le SUMA saldo_pendiente: el remanente que
     *     no se tomó en la última vacación registrada (ver
     *     registrarVacacion() más abajo). Sin esto, tomar solo una parte
     *     de los días acumulados borraría el resto — bug real detectado
     *     por Ricardo en set. 2026.
     *
     * Se generan 2.5 días por cada mes completo transcurrido, y el
     * resultado se redondea a día entero (RRHH pidió explícitamente
     * eliminar los decimales de esta pantalla).
     */
    public function calcularSaldo(Employee $trabajador, ?Carbon $hasta = null): array
    {
        $hasta = $hasta ?? now();

        if ($trabajador->fecha_ultima_vacacion) {
            $fechaCorte = Carbon::parse($trabajador->fecha_ultima_vacacion);
            $origen     = 'ultima_vacacion';
        } elseif ($trabajador->fecha_ingreso) {
            $fechaCorte = Carbon::parse($trabajador->fecha_ingreso);
            $origen     = 'fecha_ingreso';
        } else {
            return [
                'error' => 'El trabajador no tiene fecha de ingreso ni fecha de última vacación registrada — no se puede calcular el saldo.',
            ];
        }

        if ($fechaCorte->greaterThan($hasta)) {
            // Fecha de corte en el futuro (dato mal cargado) — no genera días negativos.
            $mesesTranscurridos = 0;
        } else {
            $mesesTranscurridos = $fechaCorte->diffInMonths($hasta);
        }

        $diasGenerados  = (int) round($mesesTranscurridos * self::DIAS_POR_MES);
        $saldoPendiente = floatval($trabajador->saldo_pendiente ?? 0);
        $saldoActual    = round($saldoPendiente + $diasGenerados, 2);

        return [
            'origen'              => $origen,          // 'ultima_vacacion' | 'fecha_ingreso'
            'fecha_corte'         => $fechaCorte->toDateString(),
            'meses_transcurridos' => $mesesTranscurridos,
            'dias_generados'      => $diasGenerados,
            'saldo_pendiente_cargado' => $saldoPendiente,
            'saldo_actual'        => $saldoActual,
        ];
    }

    /**
     * Registra una vacación tomada:
     *   1. Calcula el saldo ANTES de tocar nada.
     *   2. Le resta los días que se están tomando ahora — el resultado es
     *      lo que le queda pendiente (puede ser 0, o positivo si tomó
     *      menos de lo que tenía acumulado).
     *   3. Guarda ese remanente en saldo_pendiente, mueve
     *      fecha_ultima_vacacion (fecha de vuelta = fin + 1, nuevo punto
     *      de corte desde el que se generan días NUEVOS), suma al
     *      contador histórico de días tomados, y deja constancia en el
     *      historial.
     *
     * Si se toman más días de los que había en el saldo, saldo_pendiente
     * queda negativo — se guarda tal cual (no se bloquea aquí) para que
     * quede visible en la ficha; RRHH decide cómo tratarlo.
     */
    public function registrarVacacion(Employee $trabajador, Carbon $inicio, Carbon $fin, ?string $observacion = null): int
    {
        $dias = $inicio->diffInDays($fin) + 1;

        $saldoAntes     = $this->calcularSaldo($trabajador)['saldo_actual'] ?? 0;
        $saldoPendiente = round($saldoAntes - $dias, 2);

        $trabajador->update([
            'fecha_ultima_vacacion' => $fin->copy()->addDay()->toDateString(),
            'dias_tomados'          => ($trabajador->dias_tomados ?? 0) + $dias,
            'saldo_pendiente'       => $saldoPendiente,
        ]);

        $trabajador->vacaciones()->create([
            'fecha_inicio'   => $inicio->toDateString(),
            'fecha_fin'      => $fin->toDateString(),
            'dias'           => $dias,
            'observacion'    => $observacion,
            'registrado_por' => auth()->id(),
        ]);

        return $dias;
    }
}

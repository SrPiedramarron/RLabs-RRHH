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
     *     (fecha_ultima_vacacion no es null), el conteo arranca en 0
     *     desde esa fecha — es decir, desde que "volvió" de su última
     *     vacación tomada.
     *   - Si NUNCA ha tomado vacaciones en el sistema, el conteo arranca
     *     en 0 desde su fecha_ingreso.
     *
     * En ambos casos se generan 2.5 días por cada mes completo transcurrido,
     * y el resultado se redondea a día entero (RRHH pidió explícitamente
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

        $diasGenerados = (int) round($mesesTranscurridos * self::DIAS_POR_MES);

        return [
            'origen'              => $origen,          // 'ultima_vacacion' | 'fecha_ingreso'
            'fecha_corte'         => $fechaCorte->toDateString(),
            'meses_transcurridos' => $mesesTranscurridos,
            'dias_generados'      => $diasGenerados,
            'saldo_actual'        => $diasGenerados,    // arranca en 0 desde fecha_corte, sin snapshot previo
        ];
    }

    /**
     * Registra una vacación tomada: actualiza fecha_ultima_vacacion (fecha de
     * vuelta = último día del rango + 1, ya que ese es el nuevo punto de
     * corte desde el que vuelve a generar días) y suma al contador histórico
     * de días tomados.
     */
    public function registrarVacacion(Employee $trabajador, Carbon $inicio, Carbon $fin): int
    {
        $dias = $inicio->diffInDays($fin) + 1;

        $trabajador->update([
            'fecha_ultima_vacacion' => $fin->copy()->addDay()->toDateString(),
            'dias_tomados'          => ($trabajador->dias_tomados ?? 0) + $dias,
        ]);

        return $dias;
    }
}

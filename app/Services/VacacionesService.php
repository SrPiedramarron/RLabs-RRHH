<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Carbon\Carbon;

class VacacionesService
{
    const DIAS_POR_MES = 2.5; // 30 días/año ÷ 12 meses (estándar peruano)

    /**
     * Calcula el saldo actual de vacaciones de un empleado:
     * saldo_inicial (del Excel, a la fecha de corte) + días generados
     * desde esa fecha (2.5/mes trabajado) - días tomados desde esa fecha
     * (según asistencia, estado='vacaciones').
     */
    public function calcularSaldo(Employee $empleado, ?Carbon $hasta = null): array
    {
        $hasta = $hasta ?? now();

        if ($empleado->fecha_saldo_vacaciones) {
            // Tiene saldo inicial cargado (del Excel de RRHH) — se usa tal cual.
            $fechaCorte           = Carbon::parse($empleado->fecha_saldo_vacaciones);
            $saldoInicialEfectivo = floatval($empleado->saldo_vacaciones_inicial);
        } elseif ($empleado->fecha_ingreso) {
            // Empleado nuevo (o cualquiera sin snapshot inicial) — arranca
            // en 0 desde su propia fecha de ingreso. Así no depende de que
            // alguien recargue el Excel para que empiece a generar días.
            $fechaCorte           = Carbon::parse($empleado->fecha_ingreso);
            $saldoInicialEfectivo = 0.0;
        } else {
            return [
                'error' => 'No tiene fecha de ingreso ni saldo inicial cargado — no se puede calcular.',
            ];
        }

        $mesesTranscurridos = $fechaCorte->diffInMonths($hasta);
        $diasGenerados      = round($mesesTranscurridos * self::DIAS_POR_MES, 2);

        $diasTomados = AttendanceRecord::where('employee_id', $empleado->id)
            ->where('estado', 'vacaciones')
            ->whereBetween('fecha', [$fechaCorte->toDateString(), $hasta->toDateString()])
            ->count();

        $saldoActual = round($saldoInicialEfectivo + $diasGenerados - $diasTomados, 2);

        return [
            'saldo_inicial'         => round($saldoInicialEfectivo, 2),
            'fecha_corte'           => $fechaCorte->format('d/m/Y'),
            'meses_transcurridos'   => $mesesTranscurridos,
            'dias_generados'        => $diasGenerados,
            'dias_tomados'          => $diasTomados,
            'saldo_actual'          => $saldoActual,
        ];
    }
}

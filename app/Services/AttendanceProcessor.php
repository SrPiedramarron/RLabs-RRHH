<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;

class AttendanceProcessor
{
    public function processUnprocessedLogs(): void
    {
        $logs = AttendanceLog::where('procesado', false)
            ->orderBy('timestamp')
            ->get();

        // Agrupar por empleado + fecha
        $agrupados = $logs->groupBy(function ($log) {
            return $log->reloj_id . '_' . $log->timestamp->format('Y-m-d');
        });

        foreach ($agrupados as $key => $grupo) {
            [$relojId, $fecha] = explode('_', $key, 2);

            $employee = Employee::where('dni', $relojId)->first();
            if (!$employee) continue;

            // Primera marcación = entrada, última = salida
            $sorted  = $grupo->sortBy('timestamp');
            $entrada = $sorted->first();
            $salida  = $sorted->last();

            // Si solo hay una marcación, no hay salida
            if ($entrada->id === $salida->id) {
                $salida = null;
            }

            $this->calcularYGuardar($employee, $fecha, $entrada, $salida);

            // Marcar logs como procesados
            $grupo->each(fn($log) => $log->update(['procesado' => true]));
        }
    }

    private function calcularYGuardar(Employee $employee, string $fecha, $logEntrada, $logSalida): void
    {
        $schedule = $employee->schedule;

        $horaEntradaProgramada = strtotime($fecha . ' ' . $schedule->hora_entrada);
        $horaSalidaProgramada  = strtotime($fecha . ' ' . $schedule->hora_salida);
        $toleranciaSegundos    = $schedule->tolerancia_minutos * 60;

        $horaEntradaReal = $logEntrada ? $logEntrada->timestamp->timestamp : null;
        $horaSalidaReal  = $logSalida  ? $logSalida->timestamp->timestamp  : null;

        // Cálculo de tardanza
        $minutosTarde = 0;
        if ($horaEntradaReal && $horaEntradaReal > ($horaEntradaProgramada + $toleranciaSegundos)) {
            $minutosTarde = (int)(($horaEntradaReal - $horaEntradaProgramada) / 60);
        }

        // Cálculo de horas trabajadas y horas extra
        $minutosOrdinarios   = 0;
        $horasExtraDiurnas   = 0;
        $horasExtraNocturnas = 0;

        if ($horaEntradaReal && $horaSalidaReal) {
            $minutosTrabajados = (int)(($horaSalidaReal - $horaEntradaReal) / 60);

            // Descontar refrigerio si aplica
            if ($schedule->refrigerio_inicio && $schedule->refrigerio_fin) {
                $refInicio = strtotime($fecha . ' ' . $schedule->refrigerio_inicio);
                $refFin    = strtotime($fecha . ' ' . $schedule->refrigerio_fin);
                if ($horaEntradaReal < $refFin && $horaSalidaReal > $refInicio) {
                    $minutosTrabajados -= (int)(($refFin - $refInicio) / 60);
                }
            }

            $minutosJornadaNormal = (int)(($horaSalidaProgramada - $horaEntradaProgramada) / 60);
            $minutosOrdinarios    = min($minutosTrabajados, $minutosJornadaNormal);
            $minutosExtra         = max(0, $minutosTrabajados - $minutosJornadaNormal);

            // Separar horas extra diurnas/nocturnas (nocturno: 22:00-06:00)
            if ($minutosExtra > 0) {
                $nocturnoInicio      = strtotime($fecha . ' 22:00:00');
                $minutosNocturno     = max(0, (int)(($horaSalidaReal - $nocturnoInicio) / 60));
                $horasExtraNocturnas = max(0, min($minutosExtra, $minutosNocturno)) / 60;
                $horasExtraDiurnas   = ($minutosExtra / 60) - $horasExtraNocturnas;
            }
        }

        // Determinar estado del día
        $esHoliday = Holiday::whereDate('fecha', $fecha)
            ->where(fn($q) => $q->whereNull('company_id')
                ->orWhere('company_id', $employee->company_id))
            ->exists();

        $diasLabor = $schedule->dias_laborables;
        $diaSemana = (int) date('N', strtotime($fecha)); // 1=Lun, 7=Dom
        $esDescanso = !in_array($diaSemana, $diasLabor);

        $estado = match(true) {
            $esHoliday        => 'feriado',
            $esDescanso       => 'descanso',
            !$horaEntradaReal => 'ausente',
            $minutosTarde > 0 => 'tarde',
            default           => 'presente',
        };

        AttendanceRecord::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'fecha'       => $fecha,
            ],
            [
                'company_id'            => $employee->company_id,
                'location_id'           => $employee->location_id,
                'hora_entrada'          => $logEntrada?->timestamp,
                'hora_salida'           => $logSalida?->timestamp,
                'minutos_tarde'         => $minutosTarde,
                'minutos_trabajados'    => $minutosOrdinarios,
                'horas_ordinarias'      => round($minutosOrdinarios / 60, 2),
                'horas_extra_diurnas'   => round($horasExtraDiurnas, 2),
                'horas_extra_nocturnas' => round($horasExtraNocturnas, 2),
                'estado'                => $estado,
            ]
        );
    }
}
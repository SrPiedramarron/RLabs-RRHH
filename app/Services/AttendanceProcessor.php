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
        $logsNuevos = AttendanceLog::where('procesado', false)
            ->orderBy('timestamp')
            ->get();

        if ($logsNuevos->isEmpty()) return;

        $combinaciones = $logsNuevos->map(function ($log) {
            return $log->reloj_id . '_' . $log->timestamp->format('Y-m-d');
        })->unique();

        foreach ($combinaciones as $key) {
            [$relojId, $fecha] = explode('_', $key, 2);

            $employee = Employee::with('schedules')->where('reloj_id', $relojId)->orWhere('dni', $relojId)->first();
            if (!$employee) continue;

            $logs = AttendanceLog::where('reloj_id', $relojId)
                ->whereDate('timestamp', $fecha)
                ->orderBy('timestamp')
                ->get();

            $this->calcularYGuardar($employee, $fecha, $logs);
        }

        AttendanceLog::where('procesado', false)->update(['procesado' => true]);
    }

    private function calcularYGuardar(Employee $employee, string $fecha, $logs): void
    {
        // Buscar el horario correcto según el día de la semana
        $diaSemana = (int) date('N', strtotime($fecha)); // 1=lun ... 7=dom
        $schedule  = $employee->schedule;

        // Buscar horario alternativo: primero en horarios adicionales del empleado,
        // luego en todos los horarios de la empresa
        $scheduleAlternativo = $employee->schedules
            ->first(function ($s) use ($diaSemana) {
                $dias = is_array($s->dias_laborables)
                    ? $s->dias_laborables
                    : json_decode($s->dias_laborables, true);
                return in_array((string)$diaSemana, array_map('strval', $dias ?? []));
            });

        if (!$scheduleAlternativo) {
            $scheduleAlternativo = \App\Models\Schedule::where('company_id', $employee->company_id)
                ->where('id', '!=', $employee->schedule_id)
                ->get()
                ->first(function ($s) use ($diaSemana) {
                    $dias = is_array($s->dias_laborables)
                        ? $s->dias_laborables
                        : json_decode($s->dias_laborables, true);
                    return in_array((string)$diaSemana, array_map('strval', $dias ?? []));
                });
        }

        // Usar horario alternativo si el principal no cubre este día
        $diasPrincipal = is_array($schedule->dias_laborables)
            ? $schedule->dias_laborables
            : json_decode($schedule->dias_laborables, true);

        if (!in_array((string)$diaSemana, array_map('strval', $diasPrincipal ?? [])) && $scheduleAlternativo) {
            $schedule = $scheduleAlternativo;
        }

        // Refrigerio por tipo de marcación (4 = salida refrigerio, 5 = retorno refrigerio)
        $salidasRefrigerio = $logs->whereIn('tipo', [4])->sortBy('timestamp');
        $retornosRefrigerio= $logs->whereIn('tipo', [5])->sortBy('timestamp');

        // Entrada = primer log del día, Salida = último log del día
        // No confiamos en el tipo del reloj ZKBio (a veces marca entrada como tipo 1)
        $logEntrada = $logs->first();
        $logSalida  = $logs->count() > 1 ? $logs->last() : null;

        if ($logEntrada && $logSalida && $logEntrada->id === $logSalida->id) {
            $logSalida = null;
        }

        // Refrigerio real marcado en el reloj
        $logInicioRefrigerio = $salidasRefrigerio->first();
        $logFinRefrigerio    = $retornosRefrigerio->first();

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
            // FIX: Los minutos "de sobra" antes de la hora programada no cuentan.
            // El cómputo siempre arranca desde la hora programada de entrada.
            $horaInicioComputo = max($horaEntradaReal, $horaEntradaProgramada);
            $minutosTrabajados = (int)(($horaSalidaReal - $horaInicioComputo) / 60);

            // FIX: Sábados (y cualquier día cuya hora_salida_programada coincide con
            // el inicio del refrigerio) NO descuentan refrigerio.
            // La jornada termina justo cuando empieza el almuerzo, así que no aplica.
            $jornadaTerminaEnRefrigerio = $schedule->refrigerio_inicio &&
                $horaSalidaProgramada === strtotime($fecha . ' ' . $schedule->refrigerio_inicio);

            if (!$jornadaTerminaEnRefrigerio) {
                // Descontar refrigerio real si fue marcado, si no usar el del horario
                if ($logInicioRefrigerio && $logFinRefrigerio) {
                    $refInicio = $logInicioRefrigerio->timestamp->timestamp;
                    $refFin    = $logFinRefrigerio->timestamp->timestamp;
                    $minutosTrabajados -= (int)(($refFin - $refInicio) / 60);
                } elseif ($schedule->refrigerio_inicio && $schedule->refrigerio_fin) {
                    $refInicio = strtotime($fecha . ' ' . $schedule->refrigerio_inicio);
                    $refFin    = strtotime($fecha . ' ' . $schedule->refrigerio_fin);
                    if ($horaInicioComputo < $refFin && $horaSalidaReal > $refInicio) {
                        $minutosTrabajados -= (int)(($refFin - $refInicio) / 60);
                    }
                }
            }

            $minutosJornadaNormal = (int)(($horaSalidaProgramada - $horaEntradaProgramada) / 60);
            $minutosOrdinarios    = min($minutosTrabajados, $minutosJornadaNormal);
            $minutosExtra         = max(0, $minutosTrabajados - $minutosJornadaNormal);

            if ($minutosExtra > 0) {
                $nocturnoInicio      = strtotime($fecha . ' 22:00:00');
                $minutosNocturno     = max(0, (int)(($horaSalidaReal - $nocturnoInicio) / 60));
                $horasExtraNocturnas = max(0, min($minutosExtra, $minutosNocturno)) / 60;
                $horasExtraDiurnas   = ($minutosExtra / 60) - $horasExtraNocturnas;
            }
        }

        // Determinar estado
        $esHoliday = Holiday::whereDate('fecha', $fecha)
            ->where(fn($q) => $q->whereNull('company_id')
                ->orWhere('company_id', $employee->company_id))
            ->exists();

        $diasLabor  = array_map('intval', (array)$schedule->dias_laborables);
        $diaSemana  = (int) date('N', strtotime($fecha));
        $esDescanso = !in_array($diaSemana, $diasLabor);

        $estado = match(true) {
            $esHoliday        => 'feriado',
            $esDescanso       => 'descanso',
            !$horaEntradaReal => 'ausente',
            $minutosTarde > 0 => 'tarde',
            default           => 'presente',
        };

        // Resolver refrigerio: 1) marcación real tipo 4/5, 2) inferir de tipo 0, 3) fallback programado
        $inicioRefrigerio = $logInicioRefrigerio?->timestamp;
        $finRefrigerio    = $logFinRefrigerio?->timestamp;

        if (!$inicioRefrigerio && $schedule->refrigerio_inicio && $horaEntradaReal && $horaSalidaReal) {

            // Intentar inferir refrigerio de marcaciones tipo 0 intermedias
            // La entrada real ya es el primer tipo 0 — buscar los siguientes en ventana de refrigerio
            $ventanaInicio = strtotime($fecha . ' ' . $schedule->refrigerio_inicio) - 1800; // -30 min
            $ventanaFin    = strtotime($fecha . ' ' . $schedule->refrigerio_fin)    + 1800; // +30 min

            // Buscar logs tipo 0 dentro de la ventana de refrigerio
            // Sin skip por posición — filtramos directamente por ventana horaria
            $candidatos = $logs->whereIn('tipo', [0])
                ->sortBy('timestamp')
                ->filter(function ($log) use ($ventanaInicio, $ventanaFin) {
                    $ts = $log->timestamp->timestamp;
                    return $ts >= $ventanaInicio && $ts <= $ventanaFin;
                })->values();

            if ($candidatos->count() >= 2) {
                // Tenemos inicio y fin inferidos
                $inicioRefrigerio = $candidatos[0]->timestamp;
                $finRefrigerio    = $candidatos[1]->timestamp;
            } elseif ($candidatos->count() === 1) {
                // Solo inicio, fin programado
                $inicioRefrigerio = $candidatos[0]->timestamp;
                $finRefrigerio    = $fecha . ' ' . $schedule->refrigerio_fin;
            } else {
                // Sin marcaciones en ventana: usar horario programado
                $refInicioProgramado = strtotime($fecha . ' ' . $schedule->refrigerio_inicio);
                $refFinProgramado    = strtotime($fecha . ' ' . $schedule->refrigerio_fin);
                if ($horaEntradaReal <= $refInicioProgramado && $horaSalidaReal >= $refFinProgramado) {
                    $inicioRefrigerio = $fecha . ' ' . $schedule->refrigerio_inicio;
                    $finRefrigerio    = $fecha . ' ' . $schedule->refrigerio_fin;
                }
            }
        }

        // No pisar registros corregidos manualmente
        $existente = AttendanceRecord::where('employee_id', $employee->id)
            ->where('fecha', $fecha)
            ->first();
        if ($existente && $existente->corregido_manualmente) return;
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
                'inicio_refrigerio'     => $inicioRefrigerio,
                'fin_refrigerio'        => $finRefrigerio,
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

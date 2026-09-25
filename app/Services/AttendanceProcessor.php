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

    /**
     * Recalcula un rango de fechas ya procesado, sin depender del flag
     * `procesado` (para aplicar retroactivamente una corrección de lógica).
     * Respeta igual que siempre los registros con corregido_manualmente.
     */
    public function reprocesarRango(string $desde, string $hasta): int
    {
        $logs = AttendanceLog::whereDate('timestamp', '>=', $desde)
            ->whereDate('timestamp', '<=', $hasta)
            ->orderBy('timestamp')
            ->get();

        if ($logs->isEmpty()) return 0;

        $combinaciones = $logs->map(fn ($log) => $log->reloj_id . '_' . $log->timestamp->format('Y-m-d'))->unique();

        $procesados = 0;
        foreach ($combinaciones as $key) {
            [$relojId, $fecha] = explode('_', $key, 2);

            $employee = Employee::with('schedules')->where('reloj_id', $relojId)->orWhere('dni', $relojId)->first();
            if (!$employee) continue;

            $logsDelDia = AttendanceLog::where('reloj_id', $relojId)
                ->whereDate('timestamp', $fecha)
                ->orderBy('timestamp')
                ->get();

            $this->calcularYGuardar($employee, $fecha, $logsDelDia);
            $procesados++;
        }

        return $procesados;
    }

    private function calcularYGuardar(Employee $employee, string $fecha, $logs): void
    {
        // Buscar el horario correcto seg�n el d�a de la semana
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

        // Usar horario alternativo si el principal no cubre este d�a
        $diasPrincipal = is_array($schedule->dias_laborables)
            ? $schedule->dias_laborables
            : json_decode($schedule->dias_laborables, true);

        if (!in_array((string)$diaSemana, array_map('strval', $diasPrincipal ?? [])) && $scheduleAlternativo) {
            $schedule = $scheduleAlternativo;
        }

        // Refrigerio por tipo de marcaci�n (4 = salida refrigerio, 5 = retorno refrigerio)
        $salidasRefrigerio  = $logs->whereIn('tipo', [4])->sortBy('timestamp');
        $retornosRefrigerio = $logs->whereIn('tipo', [5])->sortBy('timestamp');

        // Entrada = primer log del d�a, Salida = �ltimo log del d�a
        // No confiamos en el tipo del reloj ZKBio (a veces marca entrada como tipo 1)
        $logEntrada = $logs->first();
        $logSalida  = $logs->count() > 1 ? $logs->last() : null;

        if ($logEntrada && $logSalida && $logEntrada->id === $logSalida->id) {
            $logSalida = null;
        }

        // Refrigerio real marcado en el reloj
        $logInicioRefrigerio = $salidasRefrigerio->first();
        $logFinRefrigerio    = $retornosRefrigerio->first();

        // FIX: algunos relojes (p.ej. este cliente) mandan TODAS las marcaciones
        // con el mismo tipo (1), nunca 0/4/5, por lo que el refrigerio real
        // nunca se detectaba y siempre se usaba el horario programado como
        // relleno. Igual que con entrada/salida, no confiamos en el tipo:
        // tomamos las marcaciones intermedias del día (ni la primera ni la
        // última) que caigan dentro de la ventana del refrigerio programado.
        if (!$logInicioRefrigerio && !$logFinRefrigerio && $schedule->refrigerio_inicio && $schedule->refrigerio_fin) {
            $ventanaInicioMarca = strtotime($fecha . ' ' . $schedule->refrigerio_inicio) - 1800;
            $ventanaFinMarca    = strtotime($fecha . ' ' . $schedule->refrigerio_fin)    + 1800;

            $candidatosIntermedios = $logs
                ->reject(fn ($log) => ($logEntrada && $log->id === $logEntrada->id) || ($logSalida && $log->id === $logSalida->id))
                ->sortBy('timestamp')
                ->filter(function ($log) use ($ventanaInicioMarca, $ventanaFinMarca) {
                    $ts = $log->timestamp->timestamp;
                    return $ts >= $ventanaInicioMarca && $ts <= $ventanaFinMarca;
                })->values();

            if ($candidatosIntermedios->count() >= 2) {
                $logInicioRefrigerio = $candidatosIntermedios[0];
                $logFinRefrigerio    = $candidatosIntermedios[1];
            }
        }

        $horaEntradaProgramada = strtotime($fecha . ' ' . $schedule->hora_entrada);
        $horaSalidaProgramada  = strtotime($fecha . ' ' . $schedule->hora_salida);
        $toleranciaSegundos    = $schedule->tolerancia_minutos * 60;

        $horaEntradaReal = $logEntrada ? $logEntrada->timestamp->timestamp : null;
        $horaSalidaReal  = $logSalida  ? $logSalida->timestamp->timestamp  : null;

        // C�lculo de tardanza
        $minutosTarde = 0;
        if ($horaEntradaReal && $horaEntradaReal > ($horaEntradaProgramada + $toleranciaSegundos)) {
            $minutosTarde = (int)(($horaEntradaReal - $horaEntradaProgramada) / 60);
        }

        // C�lculo de horas trabajadas y horas extra
        $minutosOrdinarios   = 0;
        $horasExtraDiurnas   = 0;
        $horasExtraNocturnas = 0;

        if ($horaEntradaReal && $horaSalidaReal) {
            // FIX: Los minutos "de sobra" antes de la hora programada no cuentan.
            // El c�mputo siempre arranca desde la hora programada de entrada.
            $horaInicioComputo = max($horaEntradaReal, $horaEntradaProgramada);
            $minutosTrabajados = (int)(($horaSalidaReal - $horaInicioComputo) / 60);

            // FIX: S�bados (y cualquier d�a cuya hora_salida_programada coincide con
            // el inicio del refrigerio) NO descuentan refrigerio.
            // La jornada termina justo cuando empieza el almuerzo, as� que no aplica.
            $jornadaTerminaEnRefrigerio = $schedule->refrigerio_inicio &&
                $horaSalidaProgramada === strtotime($fecha . ' ' . $schedule->refrigerio_inicio);

            // Minutos de refrigerio que se descontar�n (para normalizar tambi�n la jornada)
            $minutosRefrigerioDescontado = 0;

            if (!$jornadaTerminaEnRefrigerio) {
                // Descontar refrigerio real si fue marcado, si no usar el del horario
                if ($logInicioRefrigerio && $logFinRefrigerio) {
                    $refInicio = $logInicioRefrigerio->timestamp->timestamp;
                    $refFin    = $logFinRefrigerio->timestamp->timestamp;
                    $minutosRefrigerioDescontado = (int)(($refFin - $refInicio) / 60);
                    $minutosTrabajados -= $minutosRefrigerioDescontado;
                } elseif ($schedule->refrigerio_inicio && $schedule->refrigerio_fin) {
                    $refInicio = strtotime($fecha . ' ' . $schedule->refrigerio_inicio);
                    $refFin    = strtotime($fecha . ' ' . $schedule->refrigerio_fin);
                    if ($horaInicioComputo < $refFin && $horaSalidaReal > $refInicio) {
                        $minutosRefrigerioDescontado = (int)(($refFin - $refInicio) / 60);
                        $minutosTrabajados -= $minutosRefrigerioDescontado;
                    }
                }
            }

            // FIX: La jornada normal neta tambi�n debe descontar el refrigerio programado,
            // ya que $minutosTrabajados ya lo tiene descontado. Sin este ajuste,
            // el tope siempre es mayor que lo trabajado y nunca se generan horas extra.
            $minutosJornadaBruta  = (int)(($horaSalidaProgramada - $horaEntradaProgramada) / 60);
            $minutosRefrigerioProgramado = ($schedule->refrigerio_inicio && $schedule->refrigerio_fin && !$jornadaTerminaEnRefrigerio)
                ? (int)((strtotime($fecha . ' ' . $schedule->refrigerio_fin) - strtotime($fecha . ' ' . $schedule->refrigerio_inicio)) / 60)
                : 0;
            $minutosJornadaNormal = $minutosJornadaBruta - $minutosRefrigerioProgramado;

            $minutosOrdinarios = min($minutosTrabajados, $minutosJornadaNormal);
            $minutosExtra      = max(0, $minutosTrabajados - $minutosJornadaNormal);

            if ($minutosExtra > 0) {
                // Recargo de ley (D.S. 007-2002-TR): las primeras 2 horas extra
                // del día se pagan al 25% y el excedente al 35%, sin importar
                // si son antes o después de las 10pm. Confirmado con RRHH.
                // (Los nombres de campo "diurnas"/"nocturnas" se mantienen por
                // compatibilidad con boleta/PLAME/reportes, pero ahora
                // representan "primeras 2h" / "excedente".)
                $horasExtraTotales   = $minutosExtra / 60;
                $horasExtraDiurnas   = min(2, $horasExtraTotales);
                $horasExtraNocturnas = max(0, $horasExtraTotales - 2);
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

        // Resolver refrigerio: 1) marcaci�n real tipo 4/5, 2) inferir de tipo 0, 3) fallback programado
        $inicioRefrigerio = $logInicioRefrigerio?->timestamp;
        $finRefrigerio    = $logFinRefrigerio?->timestamp;

        if (!$inicioRefrigerio && $schedule->refrigerio_inicio && $horaEntradaReal && $horaSalidaReal) {

            // Intentar inferir refrigerio de marcaciones tipo 0 intermedias
            // La entrada real ya es el primer tipo 0 � buscar los siguientes en ventana de refrigerio
            $ventanaInicio = strtotime($fecha . ' ' . $schedule->refrigerio_inicio) - 1800; // -30 min
            $ventanaFin    = strtotime($fecha . ' ' . $schedule->refrigerio_fin)    + 1800; // +30 min

            // Buscar logs tipo 0 dentro de la ventana de refrigerio
            // Sin skip por posici�n � filtramos directamente por ventana horaria
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

        // Auto-asignar código PLAME 7 (Falta no justificada) cuando el día
        // es ausente y nadie de RRHH lo marcó como justificado todavía.
        // Si ya está justificado (RRHH eligió vacaciones/licencia/etc. a mano),
        // NO tocamos ese campo — se respeta lo que decidió una persona.
        $datosSuspension = [];
        if ($estado === 'ausente' && !($existente && $existente->justificado)) {
            $datosSuspension['motivo_suspension_plame'] = '7';
        }

        AttendanceRecord::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'fecha'       => $fecha,
            ],
            array_merge([
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
            ], $datosSuspension)
        );
    }
}
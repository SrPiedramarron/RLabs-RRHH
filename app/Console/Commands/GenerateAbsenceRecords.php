<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Schedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

class GenerateAbsenceRecords extends Command
{
    protected $signature   = 'attendance:generate-absences {--desde= : Fecha desde (Y-m-d)} {--hasta= : Fecha hasta (Y-m-d)}';
    protected $description = 'Genera registros de ausencia para días sin marcación';

    public function handle(): void
    {
        $desde = $this->option('desde') ?? now()->startOfMonth()->toDateString();
        $hasta = $this->option('hasta') ?? now()->toDateString();

        $this->info("Generando ausencias del {$desde} al {$hasta}...");

        $employees = Employee::with('schedule')
            ->where('active', true)
            ->where('exonerado_registro', false)
            ->get();

        $this->info("{$employees->count()} empleados activos.");

        // Cargar feriados del período
        $feriados = Holiday::whereBetween('fecha', [$desde, $hasta])
            ->pluck('fecha')
            ->map(fn($f) => Carbon::parse($f)->toDateString())
            ->toArray();

        $creados = 0;
        $omitidos = 0;

        foreach ($employees as $employee) {
            $schedule = $employee->schedule;
            $diasLaborables = $schedule->dias_laborables;

            // Fecha de ingreso del empleado
            $fechaInicio = max($desde, $employee->fecha_ingreso->toDateString());
            $fechaFin    = $employee->fecha_cese
                ? min($hasta, $employee->fecha_cese->toDateString())
                : $hasta;

            $periodo = CarbonPeriod::create($fechaInicio, $fechaFin);

            foreach ($periodo as $dia) {
                $fecha     = $dia->toDateString();
                $diaSemana = (int) $dia->dayOfWeekIso; // 1=Lun, 7=Dom

                // Saltar días no laborables
                if (!in_array($diaSemana, $diasLaborables)) continue;

                // Saltar feriados
                if (in_array($fecha, $feriados)) continue;

                // Verificar si ya existe registro
                $existe = AttendanceRecord::where('employee_id', $employee->id)
                    ->where('fecha', $fecha)
                    ->exists();

                if ($existe) {
                    $omitidos++;
                    continue;
                }

                // Crear registro de ausencia
                AttendanceRecord::create([
                    'employee_id'           => $employee->id,
                    'company_id'            => $employee->company_id,
                    'location_id'           => $employee->location_id,
                    'fecha'                 => $fecha,
                    'hora_entrada'          => null,
                    'hora_salida'           => null,
                    'minutos_tarde'         => 0,
                    'minutos_trabajados'    => 0,
                    'horas_ordinarias'      => 0,
                    'horas_extra_diurnas'   => 0,
                    'horas_extra_nocturnas' => 0,
                    'estado'                => 'ausente',
                ]);

                $creados++;
            }
        }

        $this->info("-----------------------------------");
        $this->info("Ausencias creadas: {$creados}");
        $this->info("Días con registro existente (omitidos): {$omitidos}");
        $this->info("Completado.");
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\Schedule;

class ReasignarHorarios extends Command
{
    protected $signature = 'employees:reasignar-horarios 
                            {--company_id= : ID de la empresa}
                            {--department_id= : Filtrar por departamento}
                            {--schedule_id_actual= : Solo los que tienen este horario actualmente}
                            {--schedule_id_nuevo= : Horario a asignar}
                            {--dry-run : Solo muestra los cambios sin aplicarlos}';

    protected $description = 'Reasigna horarios a empleados según filtros';

    public function handle(): void
    {
        $query = Employee::query()->with('schedule', 'department');

        if ($companyId = $this->option('company_id')) {
            $query->where('company_id', $companyId);
        }

        if ($deptId = $this->option('department_id')) {
            $query->where('department_id', $deptId);
        }

        if ($scheduleActual = $this->option('schedule_id_actual')) {
            $query->where('schedule_id', $scheduleActual);
        }

        $employees = $query->get();

        if ($employees->isEmpty()) {
            $this->warn('No se encontraron empleados con esos filtros.');
            return;
        }

        $nuevoScheduleId = $this->option('schedule_id_nuevo')
            ?? $this->ask('ID del nuevo horario a asignar');

        $schedule = Schedule::find($nuevoScheduleId);
        if (!$schedule) {
            $this->error("Horario ID {$nuevoScheduleId} no encontrado.");
            return;
        }

        $this->info("Se actualizarán {$employees->count()} empleados al horario: {$schedule->nombre}");

        $this->table(
            ['ID', 'Empleado', 'Horario Actual', 'Nuevo Horario'],
            $employees->map(fn($e) => [
                $e->id,
                $e->nombre_completo,
                $e->schedule?->nombre ?? 'Sin horario',
                $schedule->nombre,
            ])
        );

        if ($this->option('dry-run')) {
            $this->warn('[DRY RUN] No se aplicaron cambios.');
            return;
        }

        if (!$this->confirm('¿Confirmar la actualización?')) {
            $this->info('Operación cancelada.');
            return;
        }

        Employee::whereIn('id', $employees->pluck('id'))->update(['schedule_id' => $nuevoScheduleId]);

        $this->info("✅ {$employees->count()} empleados actualizados correctamente.");
    }
}
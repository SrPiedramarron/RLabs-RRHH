<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Schedule;
use App\Services\ZKTecoService;
use Illuminate\Console\Command;

class ImportEmployeesFromZKBio extends Command
{
    protected $signature   = 'zkbio:import-employees';
    protected $description = 'Importa empleados desde ZKBio a la base de datos local';

    public function handle(ZKTecoService $zkService): void
    {
        $this->info('Conectando con ZKBio...');
        $empleados = $zkService->importEmployees();
        $this->info(count($empleados) . ' empleados encontrados.');

        // Necesitamos una location y schedule por defecto
        $location = Location::first();
        $schedule = Schedule::first();

        if (!$location || !$schedule) {
            $this->error('Debes tener al menos una Sede y un Horario creados antes de importar.');
            return;
        }

        $creados     = 0;
        $actualizados = 0;
        $omitidos    = 0;

        foreach ($empleados as $emp) {
            // Limpiar DNI: quitar ceros a la izquierda
            $dni = ltrim($emp['emp_code'], '0');

            // Validar que sea DNI peruano válido (8 dígitos)
            if (strlen($dni) !== 8 || !is_numeric($dni)) {
                $this->warn("Omitiendo emp_code {$emp['emp_code']}: no parece DNI válido.");
                $omitidos++;
                continue;
            }

            // Buscar o crear empresa
            $company = null;
            if (!empty($emp['company'])) {
                $company = Company::firstOrCreate(
                    ['ruc' => $emp['company']['company_code']],
                    ['razon_social' => $emp['company']['company_name'], 'active' => true]
                );
            } else {
                $company = Company::first();
            }

            // Buscar o crear departamento
            $department = null;
            if (!empty($emp['department'])) {
                $department = Department::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'nombre'     => $emp['department']['dept_name'],
                    ]
                );
            }

            // Crear o actualizar empleado
            $existe = Employee::where('dni', $dni)
                ->where('company_id', $company->id)
                ->first();

            $datos = [
                'company_id'    => $company->id,
                'location_id'   => $location->id,
                'schedule_id'   => $schedule->id,
                'department_id' => $department?->id,
                'nombres'       => $emp['first_name'] ?? 'Sin nombre',
                'apellidos'     => $emp['last_name'] ?? 'Sin apellido',
                'dni'           => $dni,
                'cargo'         => $emp['position'] ?? null,
                'fecha_ingreso' => $emp['hire_date'] ?? now()->toDateString(),
                'reloj_uid'     => $emp['id'],
                'reloj_id'      => $emp['id'],
                'active'        => true,
            ];

            if ($existe) {
                $existe->update($datos);
                $actualizados++;
            } else {
                Employee::create($datos);
                $creados++;
            }

            $this->line("✓ {$emp['last_name']} {$emp['first_name']} — DNI: {$dni}");
        }

        $this->info("-----------------------------------");
        $this->info("Creados: {$creados}");
        $this->info("Actualizados: {$actualizados}");
        $this->info("Omitidos: {$omitidos}");
        $this->info("Importación completada.");
    }
}
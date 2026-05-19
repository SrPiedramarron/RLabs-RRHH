<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class GenerateEmployeeCredentials extends Command
{
    protected $signature = 'sumarh:generate-credentials
                            {--location= : ID o nombre de la sede}
                            {--company= : ID de la empresa}
                            {--force : Regenerar credenciales existentes}';

    protected $description = 'Genera credenciales de acceso a la PWA para empleados activos';

    public function handle(): void
    {
        $query = Employee::where('active', true)->whereNull('fecha_cese');

        if ($locationInput = $this->option('location')) {
            $location = is_numeric($locationInput)
                ? Location::find($locationInput)
                : Location::where('nombre', 'like', "%{$locationInput}%")->first();

            if (!$location) {
                $this->error("Sede no encontrada: {$locationInput}");
                return;
            }
            $query->where('location_id', $location->id);
            $this->info("Sede: {$location->nombre}");
        }

        if ($companyId = $this->option('company')) {
            $query->where('company_id', $companyId);
        }

        $employees = $query->get();
        $this->info("Empleados encontrados: {$employees->count()}");

        $creados = 0;
        $omitidos = 0;

        foreach ($employees as $emp) {
            $existe = EmployeeCredential::where('employee_id', $emp->id)->exists();

            if ($existe && !$this->option('force')) {
                $omitidos++;
                continue;
            }

            // Password por defecto: DNI del empleado
            EmployeeCredential::updateOrCreate(
                ['employee_id' => $emp->id],
                [
                    'password' => Hash::make($emp->dni),
                    'active'   => true,
                ]
            );
            $creados++;
            $this->line("✓ {$emp->nombre_completo} — DNI: {$emp->dni}");
        }

        $this->info("Credenciales creadas/actualizadas: {$creados}");
        if ($omitidos > 0) {
            $this->info("Omitidos (ya tenían credenciales): {$omitidos} — usa --force para regenerar");
        }
    }
}

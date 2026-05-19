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

    /**
     * Mapeo de nombre de departamento (reloj) → RUC de empresa en SumaRH.
     * Actualizar si se agregan nuevas empresas/áreas.
     */
    private array $departmentCompanyMap = [
        'inprocess' => '20514706302', // INDUSTRIAL PROCESS SRL
        'quantum'   => '20602076211', // QUANTUM (actualizar RUC real)
    ];

    public function handle(ZKTecoService $zkService): void
    {
        $this->info('Conectando con ZKBio...');
        $empleados = $zkService->importEmployees();
        $this->info(count($empleados) . ' empleados encontrados.');

        $location = Location::first();
        $schedule = Schedule::first();

        if (!$location || !$schedule) {
            $this->error('Debes tener al menos una Sede y un Horario creados antes de importar.');
            return;
        }

        $creados      = 0;
        $actualizados = 0;
        $omitidos     = 0;

        foreach ($empleados as $emp) {
            $dni = ltrim($emp['emp_code'], '0');

            if (strlen($dni) !== 8 || !is_numeric($dni)) {
                $this->warn("Omitiendo emp_code {$emp['emp_code']}: no parece DNI válido.");
                $omitidos++;
                continue;
            }

            // ── Resolver empresa desde el departamento del reloj ──────────────
            $company    = null;
            $department = null;

            if (!empty($emp['department']['dept_name'])) {
                $deptName   = $emp['department']['dept_name'];
                $deptKey    = strtolower(trim($deptName));
                $ruc        = $this->departmentCompanyMap[$deptKey] ?? null;

                if ($ruc) {
                    $company = Company::where('ruc', $ruc)->first();
                    if (!$company) {
                        $this->warn("No se encontró empresa con RUC {$ruc} para dept '{$deptName}'. Usando empresa por defecto.");
                    }
                }

                // Fallback: primera empresa si no hay mapeo
                $company ??= Company::first();

                $department = Department::firstOrCreate([
                    'company_id' => $company->id,
                    'nombre'     => $deptName,
                ]);

            } else {
                // Sin departamento: empresa por defecto
                $company = Company::first();
            }
            // ─────────────────────────────────────────────────────────────────

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
                $this->line("  → Ya existe, omitiendo: {$dni}");
                $omitidos++;
                continue;
            } else {
                Employee::create($datos);
                $creados++;
            }

            $this->line("✓ {$emp['last_name']} {$emp['first_name']} — DNI: {$dni} — Empresa: {$company->razon_social}");
        }

        $this->info('-----------------------------------');
        $this->info("Creados: {$creados}");
        $this->info("Actualizados: {$actualizados}");
        $this->info("Omitidos: {$omitidos}");
        $this->info('Importación completada.');
    }
}

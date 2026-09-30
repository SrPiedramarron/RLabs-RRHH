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
            // FIX (set. 2026): antes, si el departamento no matcheaba el mapeo
            // o el empleado no traía departamento, se asignaba en silencio a
            // Company::first() — es decir, "la primera empresa" de la tabla,
            // sin importar cuál fuera. Eso es exactamente el patrón de "un
            // dato terminó quemado para una sola empresa" que reportó RRHH:
            // cualquier reloj/departamento nuevo, mal escrito o sin mapear
            // caía silenciosamente en la empresa equivocada. Ahora se omite
            // el registro y se avisa con claridad, en vez de adivinar.
            $company    = null;
            $department = null;
            $deptName   = $emp['department']['dept_name'] ?? null;

            if (!empty($deptName)) {
                $deptKey = strtolower(trim($deptName));
                $ruc     = $this->departmentCompanyMap[$deptKey] ?? null;

                if ($ruc) {
                    $company = Company::where('ruc', $ruc)->first();
                    if (!$company) {
                        $this->error("DNI {$dni}: el RUC {$ruc} mapeado para el departamento '{$deptName}' no existe en la tabla de empresas. Omitido.");
                        $omitidos++;
                        continue;
                    }
                } else {
                    $this->error("DNI {$dni}: el departamento '{$deptName}' no está mapeado a ninguna empresa en \$departmentCompanyMap. Agrégalo y vuelve a correr el import. Omitido.");
                    $omitidos++;
                    continue;
                }

                $department = Department::firstOrCreate([
                    'company_id' => $company->id,
                    'nombre'     => $deptName,
                ]);
            } else {
                $this->error("DNI {$dni}: no trae departamento en ZKBio, no se puede determinar la empresa. Omitido.");
                $omitidos++;
                continue;
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

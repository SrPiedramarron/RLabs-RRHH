<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Datos de DEMOSTRACIÓN completos: catálogos + 3 empresas con empleados y 30 días de
 * marcaciones procesadas + un usuario por rol.
 *
 * Uso (solo servidor demo):  php artisan db:seed --class=RhDemoRunner --force
 *
 * PELIGRO: DemoSeeder trunca empleados, asistencia, sedes y empresas. Por eso esta clase
 * se niega a correr en producción salvo que el servidor lo autorice con ALLOW_DEMO_SEED=true.
 */
class RhDemoRunner extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && env('ALLOW_DEMO_SEED') !== true && env('ALLOW_DEMO_SEED') !== 'true') {
            $this->command->error('RhDemoRunner bloqueado en producción (borra datos). Defina ALLOW_DEMO_SEED=true solo en el servidor demo.');

            return;
        }

        $this->call([
            RolesAndPermissionsSeeder::class,
            AfpTasaSeeder::class,
            PayrollConceptSeeder::class,
            HolidaysSeeder::class,
            DemoSeeder::class,
        ]);

        $this->procesarAsistencia();

        $password = Hash::make(env('DEMO_PASSWORD', 'demo1234'));
        $company = Company::query()->orderBy('id')->first();

        foreach ([
            ['Super Admin RLabs', 'admin@rlabsrh.test', 'superadmin', 'super_admin'],
            ['Gerencia', 'gerente@rlabsrh.test', 'admin', 'gerente'],
            ['Recursos Humanos', 'rrhh@rlabsrh.test', 'rrhh', 'rrhh'],
            ['Contabilidad', 'contabilidad@rlabsrh.test', 'viewer', 'contabilidad'],
        ] as [$name, $email, $columnRole, $spatieRole]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'role' => $columnRole, 'company_id' => $columnRole === 'superadmin' ? null : $company?->id],
            );
            $user->syncRoles([$spatieRole]);
        }

        $this->asignarPermisosShield();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command->info('Demo de RLabs RH sembrada: 3 empresas, empleados, 30 días de asistencia y usuarios por rol.');
    }

    /**
     * Convierte las marcaciones sembradas en registros de asistencia diarios (lo mismo que hace
     * `asistencia:reprocesar`, pero sin su pregunta interactiva de confirmación).
     */
    private function procesarAsistencia(): void
    {
        $processor = app(AttendanceProcessor::class);
        $calcular = new \ReflectionMethod($processor, 'calcularYGuardar');
        $calcular->setAccessible(true);

        $combos = AttendanceLog::query()
            ->selectRaw('reloj_id, DATE(timestamp) as fecha')
            ->groupBy('reloj_id', 'fecha')
            ->orderBy('fecha')
            ->get();

        $empleados = Employee::with('schedules', 'schedule')->get()->keyBy('reloj_id');

        foreach ($combos as $combo) {
            $employee = $empleados->get($combo->reloj_id);
            if (! $employee) {
                continue;
            }

            $logs = AttendanceLog::where('reloj_id', $combo->reloj_id)
                ->whereDate('timestamp', $combo->fecha)
                ->orderBy('timestamp')
                ->get();

            $calcular->invoke($processor, $employee, $combo->fecha, $logs);
        }
    }

    /**
     * Genera los permisos de Filament Shield (uno por recurso/acción) y los reparte por rol:
     * super_admin = todo; rrhh = gestión de personal y asistencia; gerente y contabilidad = solo lectura
     * de sus áreas. Los nombres son los de Shield: view_any_employee, create_employee, etc.
     */
    private function asignarPermisosShield(): void
    {
        // Solo permisos: NO se generan/sobrescriben las políticas de app/Policies (las del repo son a mano)
        Artisan::call('shield:generate', ['--all' => true, '--panel' => 'admin', '--option' => 'permissions', '--no-interaction' => true]);

        $todos = Permission::all();
        $porAreas = function (array $areas, array $acciones) use ($todos) {
            return $todos->filter(function ($perm) use ($areas, $acciones) {
                foreach ($areas as $area) {
                    foreach ($acciones as $accion) {
                        if (preg_match('/^'.$accion.'_'.preg_quote($area, '/').'(::.*)?$/', $perm->name)) {
                            return true;
                        }
                    }
                }

                return false;
            });
        };

        $lectura = ['view', 'view_any'];
        $gestion = ['view', 'view_any', 'create', 'update'];

        Role::findByName('super_admin')->syncPermissions($todos);
        Role::findByName('rrhh')->syncPermissions(
            $porAreas(['employee', 'attendance', 'schedule', 'location', 'department', 'holiday', 'solicitud', 'contract', 'control::vacaciones'], $gestion)
                ->merge($porAreas(['report', 'statistics'], $lectura))
        );
        Role::findByName('gerente')->syncPermissions(
            $porAreas(['employee', 'attendance', 'company', 'location', 'department', 'report', 'statistics'], $lectura)
        );
        Role::findByName('contabilidad')->syncPermissions(
            $porAreas(['planilla', 'payroll', 'cts', 'gratificacion', 'utilidad', 'liquidacion', 'comision', 'report'], $lectura)
        );
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menús que cualquier usuario del panel veía porque no tenían política ni permiso (Recordatorios,
 * Tasas AFP, Cierre de periodos, Pagos de honorarios) o porque usaban el permiso de otra pantalla
 * (Control de Vacaciones) o directamente no se controlaban (Dashboard de Vacaciones, Dashboard de
 * Puntualidad, Reportes de Personal).
 *
 * Crea los permisos y se los da SOLO a los roles que hoy ya tienen el permiso equivalente de la
 * pantalla "de referencia", para no quitar acceso a quien lo usa. Quien no lo tenga (p. ej. un rol de
 * coordinador sin permisos de planilla) deja de ver esos menús.
 */
return new class extends Migration
{
    private const ACCIONES = ['view_any', 'view', 'create', 'update', 'delete', 'delete_any', 'force_delete', 'force_delete_any', 'restore', 'restore_any', 'replicate', 'reorder'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // clave nueva => clave de referencia (la pantalla cuyo acceso hereda)
        $recursos = [
            'afp::tasa'       => 'parametro::legal',
            'recordatorio'    => 'parametro::legal',
            'cierre::periodo' => 'planilla::liquidacion',
            'honorario::pago' => 'planilla::liquidacion',
        ];

        foreach ($recursos as $nuevo => $ref) {
            foreach (self::ACCIONES as $accion) {
                Permission::findOrCreate("{$accion}_{$nuevo}", 'web');
            }
            foreach (Role::all() as $rol) {
                foreach (self::ACCIONES as $accion) {
                    if ($this->tiene($rol, "{$accion}_{$ref}")) {
                        $rol->givePermissionTo("{$accion}_{$nuevo}");
                    }
                }
            }
        }

        // Control de Vacaciones: antes se regía por Empleados.
        foreach (['view_any', 'view', 'update'] as $accion) {
            Permission::findOrCreate("{$accion}_control::vacaciones", 'web');
        }
        foreach (Role::all() as $rol) {
            foreach (['view_any', 'view', 'update'] as $accion) {
                if ($this->tiene($rol, "{$accion}_employee")) {
                    $rol->givePermissionTo("{$accion}_control::vacaciones");
                }
            }
        }

        // Páginas: quien veía Empleados conserva los dashboards de vacaciones y los reportes de personal;
        // quien veía Asistencia conserva el dashboard de puntualidad.
        $paginas = [
            'page_DashboardVacaciones'  => 'view_any_employee',
            'page_ReportesPersonal'     => 'view_any_employee',
            'page_DashboardPuntualidad' => 'view_any_attendance::record',
        ];
        foreach ($paginas as $pagina => $ref) {
            Permission::findOrCreate($pagina, 'web');
            foreach (Role::all() as $rol) {
                if ($this->tiene($rol, $ref)) {
                    $rol->givePermissionTo($pagina);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Evita el error si el permiso de referencia no existe en esa instalación. */
    private function tiene(Role $rol, string $permiso): bool
    {
        try {
            return $rol->hasPermissionTo($permiso);
        } catch (\Throwable) {
            return false;
        }
    }

    public function down(): void
    {
        // No se revierte: quitar los permisos dejaría las pantallas sin control.
    }
};

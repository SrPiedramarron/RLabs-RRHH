<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar caché de permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── PERMISOS POR MÓDULO ──────────────────────────────────────

        $permisos = [
            // Empresas
            'ver_empresas', 'crear_empresas', 'editar_empresas', 'eliminar_empresas',

            // Sedes
            'ver_sedes', 'crear_sedes', 'editar_sedes', 'eliminar_sedes',

            // Departamentos
            'ver_departamentos', 'crear_departamentos', 'editar_departamentos', 'eliminar_departamentos',

            // Horarios
            'ver_horarios', 'crear_horarios', 'editar_horarios', 'eliminar_horarios',

            // Empleados
            'ver_empleados', 'crear_empleados', 'editar_empleados', 'eliminar_empleados',

            // Asistencia
            'ver_asistencia', 'editar_asistencia',

            // Reportes
            'ver_reportes', 'exportar_reportes',

            // Usuarios
            'ver_usuarios', 'crear_usuarios', 'editar_usuarios', 'eliminar_usuarios',

            // Roles
            'ver_roles', 'crear_roles', 'editar_roles', 'eliminar_roles',

            // Feriados
            'ver_feriados', 'crear_feriados', 'editar_feriados', 'eliminar_feriados',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // ── ROLES ────────────────────────────────────────────────────

        // SUPER ADMIN — acceso total (Shield lo maneja automáticamente)
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        // Shield asigna todos los permisos automáticamente al super_admin

        // GERENTE — solo lectura de asistencia y reportes
        $gerente = Role::firstOrCreate(['name' => 'gerente']);
        $gerente->syncPermissions([
            'ver_empresas',
            'ver_sedes',
            'ver_departamentos',
            'ver_empleados',
            'ver_asistencia',
            'ver_reportes', 'exportar_reportes',
        ]);

        // RRHH — gestiona empleados y asistencia
        $rrhh = Role::firstOrCreate(['name' => 'rrhh']);
        $rrhh->syncPermissions([
            'ver_empresas',
            'ver_sedes',
            'ver_departamentos', 'crear_departamentos', 'editar_departamentos',
            'ver_horarios', 'crear_horarios', 'editar_horarios',
            'ver_empleados', 'crear_empleados', 'editar_empleados',
            'ver_asistencia', 'editar_asistencia',
            'ver_reportes', 'exportar_reportes',
            'ver_feriados', 'crear_feriados', 'editar_feriados',
        ]);

        // CONTABILIDAD — reportes + puede gestionar usuarios
        $contabilidad = Role::firstOrCreate(['name' => 'contabilidad']);
        $contabilidad->syncPermissions([
            'ver_empresas',
            'ver_empleados',
            'ver_asistencia',
            'ver_reportes', 'exportar_reportes',
            'ver_usuarios', 'crear_usuarios', 'editar_usuarios',
        ]);

        $this->command->info('✅ Roles y permisos creados correctamente.');
        $this->command->table(
            ['Rol', 'Permisos'],
            [
                ['super_admin',   'Todos (acceso total)'],
                ['gerente',       'Ver empresas, sedes, empleados, asistencia, reportes'],
                ['rrhh',          'Gestionar empleados, asistencia, horarios, feriados + reportes'],
                ['contabilidad',  'Ver asistencia, exportar reportes, gestionar usuarios'],
            ]
        );
    }
}
<?php

namespace App\Console\Commands;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Solo lectura. Para cada rol (o el indicado) muestra qué menús del panel vería realmente un usuario
 * con ese rol, evaluando las mismas reglas que usa Filament (políticas y permisos de página). Sirve para
 * revisar que un rol no vea pantallas que no le corresponden.
 */
class MenusPorRol extends Command
{
    protected $signature = 'rrhh:menus-por-rol {rol? : Nombre del rol; sin él se listan todos} {--todos : Mostrar también el detalle de los menús que NO ve}';
    protected $description = 'Muestra qué menús del panel ve cada rol (solo lectura)';

    public function handle(): int
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        $roles = Role::query()->when($this->argument('rol'), fn ($q, $r) => $q->where('name', $r))->orderBy('name')->get();
        if ($roles->isEmpty()) {
            $this->error('No hay roles con ese nombre.');

            return self::FAILURE;
        }

        foreach ($roles as $rol) {
            // Usuario temporal con solo ese rol; todo se deshace al terminar.
            DB::beginTransaction();
            try {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $u = User::forceCreate(['name' => 'tmp', 'email' => 'tmp-' . uniqid() . '@example.invalid', 'password' => bcrypt(uniqid())]);
                $u->assignRole($rol);
                Auth::guard('web')->setUser($u);

                $visibles = [];
                $ocultos  = [];
                foreach ($panel->getResources() as $r) {
                    $item = [(string) ($r::getNavigationGroup() ?: 'Sin grupo'), $r::getNavigationLabel(), $r::canViewAny()];
                    $item[2] ? $visibles[] = $item : $ocultos[] = $item;
                }
                foreach ($panel->getPages() as $p) {
                    if (! $p::shouldRegisterNavigation()) {
                        continue;
                    }
                    $item = [(string) ($p::getNavigationGroup() ?: 'Sin grupo'), $p::getNavigationLabel(), $p::canAccess()];
                    $item[2] ? $visibles[] = $item : $ocultos[] = $item;
                }

                $this->newLine();
                $this->info("== Rol: {$rol->name} (" . $rol->permissions()->count() . ' permisos) ==');
                $porGrupo = collect($visibles)->groupBy(0)->sortKeys();
                foreach ($porGrupo as $grupo => $items) {
                    $this->line('  <fg=cyan>' . $grupo . '</>: ' . $items->pluck(1)->sort()->implode(', '));
                }
                if (! $visibles) {
                    $this->line('  (no ve ningún menú)');
                }
                if ($this->option('todos') && $ocultos) {
                    $this->line('  <fg=gray>No ve: ' . collect($ocultos)->pluck(1)->sort()->implode(', ') . '</>');
                }
            } finally {
                Auth::guard('web')->forgetUser();
                DB::rollBack();
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        }

        return self::SUCCESS;
    }
}

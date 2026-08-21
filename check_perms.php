<?php

$user = \App\Models\User::first();

echo "Usuario: " . $user->email . PHP_EOL;
echo "Roles: " . $user->getRoleNames()->implode(',') . PHP_EOL;
echo "Es super_admin: " . ($user->hasRole('super_admin') ? 'SI' : 'NO') . PHP_EOL;
echo PHP_EOL;

echo "Permisos planilla existentes en BD:" . PHP_EOL;
$perms = \Spatie\Permission\Models\Permission::where('name', 'like', '%planilla%')->get();
if ($perms->isEmpty()) {
    echo "  (ninguno — hay que correr shield:generate)" . PHP_EOL;
} else {
    foreach ($perms as $p) {
        echo "  - " . $p->name . PHP_EOL;
    }
}

echo PHP_EOL;
echo "Puede ver_any planilla: " . ($user->can('view_any_planilla::liquidacion') ? 'SI' : 'NO') . PHP_EOL;

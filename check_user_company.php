<?php
$u = \App\Models\User::where('email', 'ricardosanchezmarquez@gmail.com')->first();
echo "company_id: " . ($u->company_id ?? 'NULL') . PHP_EOL;
echo "Roles: " . $u->getRoleNames()->implode(',') . PHP_EOL;

echo PHP_EOL . "Todos los usuarios y su company_id:" . PHP_EOL;
\App\Models\User::all(['id', 'email', 'company_id'])->each(fn($x) => print_r($x->toArray()));

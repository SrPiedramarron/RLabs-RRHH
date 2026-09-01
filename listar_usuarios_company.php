<?php
\App\Models\User::with('company')->get(['id', 'name', 'email', 'company_id'])->each(function ($u) {
    echo "ID {$u->id} | {$u->email} | company_id: " . ($u->company_id ?? 'NULL') .
         " | roles: " . $u->getRoleNames()->implode(',') . PHP_EOL;
});

<?php

namespace App\Policies;

use App\Models\CierrePeriodo;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CierrePeriodoPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_cierre::periodo');
    }

    public function view(User $user, CierrePeriodo $cierrePeriodo): bool
    {
        return $user->can('view_cierre::periodo');
    }

    public function create(User $user): bool
    {
        return $user->can('create_cierre::periodo');
    }

    public function update(User $user, CierrePeriodo $cierrePeriodo): bool
    {
        return $user->can('update_cierre::periodo');
    }

    public function delete(User $user, CierrePeriodo $cierrePeriodo): bool
    {
        return $user->can('delete_cierre::periodo');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_cierre::periodo');
    }

    public function forceDelete(User $user, CierrePeriodo $cierrePeriodo): bool
    {
        return $user->can('force_delete_cierre::periodo');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_cierre::periodo');
    }

    public function restore(User $user, CierrePeriodo $cierrePeriodo): bool
    {
        return $user->can('restore_cierre::periodo');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_cierre::periodo');
    }

    public function replicate(User $user, CierrePeriodo $cierrePeriodo): bool
    {
        return $user->can('replicate_cierre::periodo');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_cierre::periodo');
    }
}

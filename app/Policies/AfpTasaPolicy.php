<?php

namespace App\Policies;

use App\Models\AfpTasa;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AfpTasaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_afp::tasa');
    }

    public function view(User $user, AfpTasa $afpTasa): bool
    {
        return $user->can('view_afp::tasa');
    }

    public function create(User $user): bool
    {
        return $user->can('create_afp::tasa');
    }

    public function update(User $user, AfpTasa $afpTasa): bool
    {
        return $user->can('update_afp::tasa');
    }

    public function delete(User $user, AfpTasa $afpTasa): bool
    {
        return $user->can('delete_afp::tasa');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_afp::tasa');
    }

    public function forceDelete(User $user, AfpTasa $afpTasa): bool
    {
        return $user->can('force_delete_afp::tasa');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_afp::tasa');
    }

    public function restore(User $user, AfpTasa $afpTasa): bool
    {
        return $user->can('restore_afp::tasa');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_afp::tasa');
    }

    public function replicate(User $user, AfpTasa $afpTasa): bool
    {
        return $user->can('replicate_afp::tasa');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_afp::tasa');
    }
}

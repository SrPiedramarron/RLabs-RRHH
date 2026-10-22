<?php

namespace App\Policies;

use App\Models\HonorarioPago;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HonorarioPagoPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_honorario::pago');
    }

    public function view(User $user, HonorarioPago $honorarioPago): bool
    {
        return $user->can('view_honorario::pago');
    }

    public function create(User $user): bool
    {
        return $user->can('create_honorario::pago');
    }

    public function update(User $user, HonorarioPago $honorarioPago): bool
    {
        return $user->can('update_honorario::pago');
    }

    public function delete(User $user, HonorarioPago $honorarioPago): bool
    {
        return $user->can('delete_honorario::pago');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_honorario::pago');
    }

    public function forceDelete(User $user, HonorarioPago $honorarioPago): bool
    {
        return $user->can('force_delete_honorario::pago');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_honorario::pago');
    }

    public function restore(User $user, HonorarioPago $honorarioPago): bool
    {
        return $user->can('restore_honorario::pago');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_honorario::pago');
    }

    public function replicate(User $user, HonorarioPago $honorarioPago): bool
    {
        return $user->can('replicate_honorario::pago');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_honorario::pago');
    }
}
